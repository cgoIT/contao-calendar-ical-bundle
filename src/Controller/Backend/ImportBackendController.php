<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\Backend;

use Cgoit\ContaoCalendarIcalBundle\Drivers\DC_IcalImport;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsFileImporter;
use Cgoit\ContaoCalendarIcalBundle\Import\TimezoneCheck;
use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\Controller\Backend\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Message;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The import form of the calendar in the back end as a Symfony route that renders the widgets of Contao.
 *
 * Step 1 asks for the ics file and the settings of the import. If the time zone of the file differs from the
 * one of the system (or the file has none), step 2 asks how to proceed. The state in between is kept in the session.
 */
#[Route('/%contao.backend.route_prefix%/calendar-ical/import/{id}',
    name: self::ROUTE_NAME,
    requirements: ['id' => '\d+'],
    defaults: ['_scope' => 'backend'])
]
final class ImportBackendController extends AbstractBackendController
{
    public const string ROUTE_NAME = 'cgoit_calendar_ical_import';

    private const string SESSION_KEY = 'cgoit_calendar_ical_import.';

    public function __construct(
        private readonly DC_IcalImport $dataContainer,
        private readonly IcsFileImporter $importer,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly UrlGeneratorInterface $router,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'calendar');
        $this->denyAccessUnlessGranted(ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR, $id);

        $calendar = CalendarModel::findById($id);

        if (null === $calendar) {
            throw new NotFoundHttpException('Calendar not found');
        }

        System::loadLanguageFile('tl_calendar_events');
        System::loadLanguageFile('tl_files');

        $session = $request->getSession();
        $state = $session->get(self::SESSION_KEY.$id);
        $state = \is_array($state) ? $state : null;

        // The user cancels at step 2
        if (null !== $state && $request->request->has('abort')) {
            $this->importer->delete($state['file']);
            $session->remove(self::SESSION_KEY.$id);

            return new RedirectResponse($request->getUri());
        }

        return null === $state
            ? $this->handleUploadStep($request, $calendar)
            : $this->handleTimezoneStep($request, $calendar, $state);
    }

    private function handleUploadStep(Request $request, CalendarModel $calendar): Response
    {
        $this->dataContainer->initialize(
            (int) $calendar->id,
            'upload',
            [
                'startDate' => $this->dataContainer->getDefaultDate(true),
                'endDate' => $this->dataContainer->getDefaultDate(false),
                'timeshift' => 0,
            ],
        );

        $boxes = $this->renderBoxes();

        if ($this->dataContainer->isValidSubmit()) {
            $values = $this->dataContainer->getValues();
            $file = $this->upload();

            if (null !== $file) {
                $state = [
                    'file' => $file,
                    'startDate' => $values['startDate'],
                    'endDate' => $values['endDate'] ?? '',
                    'timeshift' => (int) ($values['timeshift'] ?? 0),
                    'deleteCalendar' => !empty($values['deleteCalendar']),
                    'filterEventTitle' => (string) ($values['filterEventTitle'] ?? ''),
                ];

                $response = $this->continueWithFile($request, $calendar, $state);

                if (null !== $response) {
                    return $response;
                }
            }

            return new RedirectResponse($request->getUri());
        }

        return $this->renderImportForm($calendar, $boxes, true, null, $GLOBALS['TL_LANG']['tl_calendar_events']['import'][0]);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function handleTimezoneStep(Request $request, CalendarModel $calendar, array $state): Response
    {
        $differs = 'differs' === $state['mode'];

        $this->dataContainer->initialize(
            (int) $calendar->id,
            $differs ? 'timezone_differs' : 'timezone_missing',
            [
                'timezone' => (string) Config::get('timeZone'),
            ],
        );

        $boxes = $this->renderBoxes();

        if ($this->dataContainer->isValidSubmit()) {
            $values = $this->dataContainer->getValues();

            $this->runImport($calendar, $state, $differs ? null : (string) $values['timezone'], $differs && !empty($values['correctTimezone']));
            $request->getSession()->remove(self::SESSION_KEY.$calendar->id);

            return $this->redirectToCalendar($calendar);
        }

        $info = $differs
            ? \sprintf($GLOBALS['TL_LANG']['tl_calendar_events']['confirmationTimezone'], Config::get('timeZone'), $state['fileTimezone'])
            : \sprintf($GLOBALS['TL_LANG']['tl_calendar_events']['confirmationMissingTZ'], Config::get('timeZone'));

        return $this->renderImportForm($calendar, $boxes, false, $info, $GLOBALS['TL_LANG']['tl_calendar_events']['proceed'][0], true);
    }

    /**
     * Checks the time zone of the file and imports it or asks the user how to proceed.
     *
     * @param array<string, mixed> $state
     */
    private function continueWithFile(Request $request, CalendarModel $calendar, array $state): Response|null
    {
        try {
            $cal = $this->importer->load($state['file'], $calendar);
        } catch (\InvalidArgumentException $e) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['ics_parse_error']);
            $this->logger->error('Could not import ics file "'.$state['file'].'": '.$e->getMessage());
            $this->importer->delete($state['file']);

            return null;
        }

        $check = $this->importer->checkTimezone($cal);

        if (TimezoneCheck::Matches === $check) {
            $this->runImport($calendar, $state, null, false);

            return $this->redirectToCalendar($calendar);
        }

        $state['mode'] = TimezoneCheck::Differs === $check ? 'differs' : 'missing';
        $state['fileTimezone'] = $this->importer->getTimezone($cal)[1] ?? '';

        $request->getSession()->set(self::SESSION_KEY.$calendar->id, $state);

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function runImport(CalendarModel $calendar, array $state, string|null $timezone, bool $useSystemTimezone): void
    {
        $dateFormat = (string) Config::get('dateFormat');

        try {
            $cal = $this->importer->load($state['file'], $calendar);

            $this->importer->import(
                $state['file'],
                $cal,
                $calendar,
                new Date($state['startDate'], $dateFormat),
                new Date('' !== $state['endDate'] ? $state['endDate'] : $this->dataContainer->getDefaultDate(false), $dateFormat),
                $timezone,
                $useSystemTimezone,
                $state['deleteCalendar'],
                $state['timeshift'],
                $state['filterEventTitle'],
            );

            Message::addConfirmation($GLOBALS['TL_LANG']['tl_calendar_events']['import_done']);
        } catch (\Throwable $e) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['ics_parse_error']);
            $this->logger->error('Could not import ics file "'.$state['file'].'": '.$e->getMessage());
            $this->importer->delete($state['file']);
        }
    }

    /**
     * Uploads the file of the form into the temporary directory.
     *
     * @return string|null The path of the uploaded ics file
     */
    private function upload(): string|null
    {
        $uploaded = $this->dataContainer->upload('system/tmp');
        $files = [];

        foreach ($uploaded as $path) {
            $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

            if ('ics' !== $extension) {
                Message::addError(\sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $extension));
                $this->importer->delete((string) $path);

                continue;
            }

            $files[] = (string) $path;
        }

        if ([] === $files) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['all_fields']);

            return null;
        }

        if (\count($files) > 1) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['only_one_file']);

            foreach ($files as $file) {
                $this->importer->delete($file);
            }

            return null;
        }

        return $files[0];
    }

    /**
     * @return list<array{key: string, class: string, label: string, widgets: list<string>}>
     */
    private function renderBoxes(): array
    {
        return array_map(
            fn (array $box): array => [
                'key' => $box['key'],
                'class' => $box['class'],
                'label' => $GLOBALS['TL_LANG']['tl_calendar_events'][$box['key']] ?? $box['key'],
                'widgets' => array_map($this->dataContainer->renderField(...), $box['fields']),
            ],
            $this->dataContainer->getBoxes(),
        );
    }

    /**
     * @param list<array{key: string, class: string, label: string, widgets: list<string>}> $boxes
     */
    private function renderImportForm(CalendarModel $calendar, array $boxes, bool $withUploader, string|null $info, string $submit, bool $withAbort = false): Response
    {
        return $this->render('@Contao/backend/calendar_ical/import.html.twig', [
            'headline' => $GLOBALS['TL_LANG']['MSC']['import_calendar'][0],
            'table' => DC_IcalImport::TABLE,
            'boxes' => $boxes,
            'uploader' => $withUploader ? $this->dataContainer->renderUploader() : null,
            'upload_label' => $GLOBALS['TL_LANG']['tl_calendar_events']['icssource'],
            'info' => $info,
            'submit' => $submit,
            'abort' => $withAbort ? $GLOBALS['TL_LANG']['tl_calendar_events']['cancel'] : null,
            'back_url' => $this->getBackUrl((int) $calendar->id),
            'request_token' => $this->csrfTokenManager->getDefaultTokenValue(),
            'max_file_size' => Config::get('maxFileSize'),
            'has_errors' => $this->dataContainer->hasErrors(),
            'message' => Message::generate(),
        ]);
    }

    private function redirectToCalendar(CalendarModel $calendar): RedirectResponse
    {
        return new RedirectResponse($this->getBackUrl((int) $calendar->id));
    }

    private function getBackUrl(int $calendarId): string
    {
        return $this->router->generate('contao_backend', ['do' => 'calendar', 'table' => 'tl_calendar_events', 'id' => $calendarId]);
    }
}
