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
use Cgoit\ContaoCalendarIcalBundle\Import\CsvImport;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The import form of the calendar in the back end as a Symfony route that renders the widgets of Contao.
 *
 * Step 1 asks for the file (ics or csv) and the settings of the import. For an ics file the time zone is clarified
 * in step 2 if it differs from the one of the system (or the file has none), for a csv file the columns are assigned
 * to the fields of the events. The state in between is kept in the session.
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
        private readonly CsvImport $csvImport,
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
            $this->deleteFile($state);
            $session->remove(self::SESSION_KEY.$id);

            return new RedirectResponse($request->getUri());
        }

        if (null === $state) {
            return $this->handleUploadStep($request, $calendar);
        }

        return 'csv' === $state['type']
            ? $this->handleCsvStep($request, $calendar, $state)
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
                    'type' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
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
     * @param array<string, mixed> $state
     */
    private function handleCsvStep(Request $request, CalendarModel $calendar, array $state): Response
    {
        // Another encoding changes the columns and the preview
        $encoding = $request->request->has('encoding') && \in_array($request->request->get('encoding'), ['utf8', 'latin1'], true)
            ? (string) $request->request->get('encoding')
            : 'utf8';

        $preview = $this->csvImport->readPreview($state['file'], $encoding);

        $this->dataContainer->initialize(
            (int) $calendar->id,
            'csv',
            [
                'encoding' => $encoding,
                'dateFormat' => $GLOBALS['TL_LANG']['tl_calendar_events']['dateFormat'],
                'timeFormat' => $GLOBALS['TL_LANG']['tl_calendar_events']['timeFormat'],
            ],
        );
        $this->dataContainer->defineMappingFields($preview['header'], $this->csvImport->getCalendarFields());

        $boxes = $this->renderBoxes();

        if ($this->dataContainer->isValidSubmit() && $request->request->has('import')) {
            $this->runCsvImport($calendar, $state, $this->dataContainer->getValues(), \count($preview['header']));
            $request->getSession()->remove(self::SESSION_KEY.$calendar->id);

            return $this->redirectToCalendar($calendar);
        }

        return $this->renderImportForm($calendar, $boxes, false, null, $GLOBALS['TL_LANG']['tl_calendar_events']['proceed'][0], true, $preview);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $values
     */
    private function runCsvImport(CalendarModel $calendar, array $state, array $values, int $columns): void
    {
        $dateFormat = (string) Config::get('dateFormat');
        $mapping = [];

        for ($i = 0; $i < $columns; ++$i) {
            if (!empty($values['mapping_'.$i])) {
                $mapping[] = [$i, (string) $values['mapping_'.$i]];
            }
        }

        try {
            $skipped = $this->csvImport->import(
                $state['file'],
                (int) $calendar->id,
                $mapping,
                (string) $values['encoding'],
                (string) $values['dateFormat'],
                (string) $values['timeFormat'],
                new Date($state['startDate'], $dateFormat),
                '' !== $state['endDate'] ? new Date($state['endDate'], $dateFormat) : null,
                $state['deleteCalendar'],
                $state['timeshift'],
                $state['filterEventTitle'],
            );

            Message::addConfirmation($GLOBALS['TL_LANG']['tl_calendar_events']['import_done']);

            if ([] !== $skipped) {
                Message::addError(\sprintf($GLOBALS['TL_LANG']['ERR']['csv_records_skipped'], \count($skipped), implode(', ', \array_slice($skipped, 0, 20)).(\count($skipped) > 20 ? ', ...' : '')));
            }
        } catch (\Throwable $e) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['csv_parse_error']);
            $this->logger->error('Could not import csv file "'.$state['file'].'": '.$e->getMessage());
        } finally {
            $this->csvImport->delete($state['file']);
        }
    }

    /**
     * Checks the time zone of the file and imports it or asks the user how to proceed.
     *
     * @param array<string, mixed> $state
     */
    private function continueWithFile(Request $request, CalendarModel $calendar, array $state): Response|null
    {
        if ('csv' === $state['type']) {
            $this->continueWithCsvFile($request, $calendar, $state);

            return null;
        }

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
    private function continueWithCsvFile(Request $request, CalendarModel $calendar, array $state): void
    {
        try {
            $header = $this->csvImport->readPreview($state['file'], 'utf8', 0)['header'];
        } catch (\Throwable $e) {
            $header = [];
            $this->logger->error('Could not read csv file "'.$state['file'].'": '.$e->getMessage());
        }

        if ([] === $header) {
            Message::addError($GLOBALS['TL_LANG']['ERR']['csv_parse_error']);
            $this->csvImport->delete($state['file']);

            return;
        }

        $request->getSession()->set(self::SESSION_KEY.$calendar->id, $state);
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

            if (!\in_array($extension, ['ics', 'csv'], true)) {
                Message::addError(\sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $extension));
                (new Filesystem())->remove(System::getContainer()->getParameter('kernel.project_dir').'/'.$path);

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
                $this->deleteFile(['file' => $file, 'type' => strtolower(pathinfo($file, PATHINFO_EXTENSION))]);
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
     * @param array{header: list<string>, rows: list<list<string>>}|null                    $preview
     */
    private function renderImportForm(CalendarModel $calendar, array $boxes, bool $withUploader, string|null $info, string $submit, bool $withAbort = false, array|null $preview = null): Response
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
            'preview' => $preview,
            'preview_label' => $GLOBALS['TL_LANG']['tl_calendar_events']['preview'],
            'check' => null !== $preview ? $GLOBALS['TL_LANG']['tl_calendar_events']['check'] : null,
            'back_url' => $this->getBackUrl((int) $calendar->id),
            'request_token' => $this->csrfTokenManager->getDefaultTokenValue(),
            'max_file_size' => Config::get('maxFileSize'),
            'has_errors' => $this->dataContainer->hasErrors(),
            'message' => Message::generate(),
        ]);
    }

    /**
     * Deletes the uploaded file of an import.
     *
     * @param array<string, mixed> $state
     */
    private function deleteFile(array $state): void
    {
        if ('csv' === $state['type']) {
            $this->csvImport->delete($state['file']);
        } else {
            $this->importer->delete($state['file']);
        }
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
