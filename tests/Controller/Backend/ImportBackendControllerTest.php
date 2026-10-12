<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Controller\Backend;

use Cgoit\ContaoCalendarIcalBundle\Controller\Backend\ImportBackendController;
use Cgoit\ContaoCalendarIcalBundle\Drivers\DC_IcalImport;
use Cgoit\ContaoCalendarIcalBundle\Import\CsvImport;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsFileImporter;
use Cgoit\ContaoCalendarIcalBundle\Import\TimezoneCheck;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CalendarModel;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Model\Registry;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Tests the steps of the import that end in a redirect. The steps that render the form need the Contao back end.
 */
final class ImportBackendControllerTest extends ContaoTestCase
{
    private const string URI = 'http://localhost/contao/calendar-ical/import/3';

    private const string SESSION_KEY = 'cgoit_calendar_ical_import.3';

    private DC_IcalImport&MockObject $dataContainer;

    private IcsFileImporter&MockObject $icsImporter;

    private CsvImport&MockObject $csvImport;

    private Session $session;

    /**
     * @var array<string, bool>
     */
    private array $denied = [];

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->bootContao();
        $this->session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($this->session);
        $container->get('request_stack')->push($request);

        $matcher = $this->createStub(ScopeMatcher::class);
        $matcher
            ->method('isBackendRequest')
            ->willReturn(true)
        ;
        $container->set('contao.routing.scope_matcher', $matcher);

        $GLOBALS['TL_CONFIG']['dateFormat'] = 'Y-m-d';
        $GLOBALS['TL_LANG']['ERR'] = ['all_fields' => 'all fields', 'filetype' => 'type %s', 'only_one_file' => 'one file', 'ics_parse_error' => 'ics error', 'csv_parse_error' => 'csv error', 'csv_records_skipped' => '%d skipped (%s)'];
        $GLOBALS['TL_LANG']['tl_calendar_events'] = ['import_done' => 'done', 'dateFormat' => 'd.m.Y', 'timeFormat' => 'H:i'];

        $calendar = new CalendarModel();
        $calendar->id = 3;
        $calendar->title = 'Club';
        Registry::getInstance()->register($calendar);

        $this->dataContainer = $this->createMock(DC_IcalImport::class);
        $this->dataContainer
            ->method('getBoxes')
            ->willReturn([])
        ;
        $this->icsImporter = $this->createMock(IcsFileImporter::class);
        $this->csvImport = $this->createMock(CsvImport::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_LANG']['ERR'], $GLOBALS['TL_LANG']['tl_calendar_events']);

        parent::tearDown();
    }

    public function testDeniesAccessWithoutTheModule(): void
    {
        $this->denied[ContaoCorePermissions::USER_CAN_ACCESS_MODULE] = true;

        $this->expectException(AccessDeniedException::class);

        $this->call($this->request());
    }

    public function testDeniesAccessWithoutTheRightToEditTheCalendar(): void
    {
        $this->denied[ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR] = true;

        $this->expectException(AccessDeniedException::class);

        $this->call($this->request());
    }

    public function testImportsAnIcsFileWithTheTimezoneOfTheSystem(): void
    {
        $this->submit(['startDate' => '2026-01-01', 'endDate' => '2026-12-31', 'timeshift' => 2, 'deleteCalendar' => true, 'filterEventTitle' => 'Training'], ['system/tmp/club.ics']);
        $this->icsImporter
            ->method('load')
            ->willReturn(new Vcalendar())
        ;

        $this->icsImporter
            ->method('checkTimezone')
            ->willReturn(TimezoneCheck::Matches)
        ;

        $this->icsImporter
            ->expects($this->once())
            ->method('import')
            ->with(
                'system/tmp/club.ics',
                $this->isInstanceOf(Vcalendar::class),
                $this->isInstanceOf(CalendarModel::class),
                $this->isInstanceOf(Date::class),
                $this->isInstanceOf(Date::class),
                null,
                false,
                true,
                2,
                'Training',
            )
        ;

        $response = $this->call($this->request());

        $this->assertSame('/back', $response->getTargetUrl());
        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    public function testAsksForTheTimezoneIfTheFileHasAnotherOne(): void
    {
        $this->submit(['startDate' => '2026-01-01', 'endDate' => '', 'timeshift' => 0], ['system/tmp/club.ics']);
        $cal = new Vcalendar();
        $cal->setXprop(IcalInterface::X_WR_TIMEZONE, 'America/New_York');
        $this->icsImporter
            ->method('load')
            ->willReturn($cal)
        ;

        $this->icsImporter
            ->method('checkTimezone')
            ->willReturn(TimezoneCheck::Differs)
        ;

        $this->icsImporter
            ->method('getTimezone')
            ->willReturn(['X-WR-TIMEZONE', 'America/New_York'])
        ;

        $this->icsImporter
            ->expects($this->never())
            ->method('import')
        ;

        $response = $this->call($this->request());

        $this->assertSame(self::URI, $response->getTargetUrl());

        $state = $this->session->get(self::SESSION_KEY);
        $this->assertSame('differs', $state['mode']);
        $this->assertSame('America/New_York', $state['fileTimezone']);
        $this->assertSame('ics', $state['type']);
        $this->assertSame('system/tmp/club.ics', $state['file']);
    }

    public function testImportsWithTheChosenTimezoneIfTheFileHasNone(): void
    {
        $this->session->set(self::SESSION_KEY, $this->state(['mode' => 'missing']));
        $this->submit(['timezone' => 'Europe/Paris']);
        $this->icsImporter
            ->method('load')
            ->willReturn(new Vcalendar())
        ;

        $this->icsImporter
            ->expects($this->once())
            ->method('import')
            ->with('system/tmp/club.ics', $this->anything(), $this->anything(), $this->anything(), $this->anything(), 'Europe/Paris', false, false, 0, '')
        ;

        $response = $this->call($this->request());

        $this->assertSame('/back', $response->getTargetUrl());
        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    public function testUsesTheTimezoneOfTheSystemIfTheUserCorrectsIt(): void
    {
        $this->session->set(self::SESSION_KEY, $this->state(['mode' => 'differs', 'fileTimezone' => 'America/New_York']));
        $this->submit(['correctTimezone' => '1']);
        $this->icsImporter
            ->method('load')
            ->willReturn(new Vcalendar())
        ;

        $this->icsImporter
            ->expects($this->once())
            ->method('import')
            ->with('system/tmp/club.ics', $this->anything(), $this->anything(), $this->anything(), $this->anything(), null, true, false, 0, '')
        ;

        $this->call($this->request());
    }

    public function testCancelDeletesTheUploadedIcsFile(): void
    {
        $this->session->set(self::SESSION_KEY, $this->state(['mode' => 'missing']));
        $this->icsImporter
            ->expects($this->once())
            ->method('delete')
            ->with('system/tmp/club.ics')
        ;

        $this->csvImport
            ->expects($this->never())
            ->method('delete')
        ;

        $response = $this->call($this->request(['abort' => '1']));

        $this->assertSame(self::URI, $response->getTargetUrl());
        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    public function testCancelDeletesTheUploadedCsvFile(): void
    {
        $this->session->set(self::SESSION_KEY, $this->state(['type' => 'csv', 'file' => 'system/tmp/events.csv']));
        $this->csvImport
            ->expects($this->once())
            ->method('delete')
            ->with('system/tmp/events.csv')
        ;

        $this->icsImporter
            ->expects($this->never())
            ->method('delete')
        ;

        $this->call($this->request(['abort' => '1']));

        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    public function testRejectsASubmitWithoutFile(): void
    {
        $this->submit(['startDate' => '2026-01-01'], []);
        $this->icsImporter
            ->expects($this->never())
            ->method('import')
        ;

        $response = $this->call($this->request());

        $this->assertSame(self::URI, $response->getTargetUrl());
        $this->assertFalse($this->session->has(self::SESSION_KEY));
        $this->assertNotEmpty($this->session->getFlashBag()->peekAll());
    }

    public function testRejectsOtherFileTypes(): void
    {
        $this->submit(['startDate' => '2026-01-01'], ['system/tmp/notes.txt']);
        $this->icsImporter
            ->expects($this->never())
            ->method('load')
        ;

        $this->call($this->request());

        $this->assertFalse($this->session->has(self::SESSION_KEY));
        $this->assertNotEmpty($this->session->getFlashBag()->peekAll());
    }

    public function testContinuesWithTheAssignmentOfTheColumnsForACsvFile(): void
    {
        $this->submit(['startDate' => '2026-01-01', 'endDate' => '', 'timeshift' => 0], ['system/tmp/events.csv']);
        $this->csvImport
            ->method('readPreview')
            ->willReturn(['header' => ['Titel', 'Datum'], 'rows' => []])
        ;

        $this->icsImporter
            ->expects($this->never())
            ->method('checkTimezone')
        ;

        $response = $this->call($this->request());

        $this->assertSame(self::URI, $response->getTargetUrl());
        $this->assertSame('csv', $this->session->get(self::SESSION_KEY)['type']);
        $this->assertSame('system/tmp/events.csv', $this->session->get(self::SESSION_KEY)['file']);
    }

    public function testRejectsACsvFileWithoutHeader(): void
    {
        $this->submit(['startDate' => '2026-01-01'], ['system/tmp/events.csv']);
        $this->csvImport
            ->method('readPreview')
            ->willReturn(['header' => [], 'rows' => []])
        ;

        $this->csvImport
            ->expects($this->once())
            ->method('delete')
            ->with('system/tmp/events.csv')
        ;

        $this->call($this->request());

        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    public function testImportsACsvFileWithTheAssignedColumns(): void
    {
        $this->session->set(self::SESSION_KEY, $this->state(['type' => 'csv', 'file' => 'system/tmp/events.csv']));
        $this->submit(['encoding' => 'utf8', 'dateFormat' => 'd.m.Y', 'timeFormat' => 'H:i', 'mapping_0' => 'title', 'mapping_1' => 'startDate', 'mapping_2' => '']);
        $this->csvImport
            ->method('readPreview')
            ->willReturn(['header' => ['Titel', 'Datum', 'Ort'], 'rows' => []])
        ;

        $this->csvImport
            ->method('getCalendarFields')
            ->willReturn(['title' => 'Titel'])
        ;

        $this->csvImport
            ->expects($this->once())
            ->method('delete')
            ->with('system/tmp/events.csv')
        ;

        $this->csvImport
            ->expects($this->once())
            ->method('import')
            ->with('system/tmp/events.csv', 3, [[0, 'title'], [1, 'startDate']], 'utf8', 'd.m.Y', 'H:i', $this->isInstanceOf(Date::class), null, false, 0, '')
            ->willReturn([4])
        ;

        $response = $this->call($this->request(['import' => '1']));

        $this->assertSame('/back', $response->getTargetUrl());
        $this->assertFalse($this->session->has(self::SESSION_KEY));
    }

    public function testUnknownCalendarIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->call($this->request(), 4);
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $uploaded
     */
    private function submit(array $values, array $uploaded = []): void
    {
        $this->dataContainer
            ->method('isValidSubmit')
            ->willReturn(true)
        ;

        $this->dataContainer
            ->method('getValues')
            ->willReturn($values)
        ;

        $this->dataContainer
            ->method('upload')
            ->willReturn($uploaded)
        ;

        $this->dataContainer
            ->method('getDefaultDate')
            ->willReturn('2026-12-31')
        ;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function request(array $post = []): Request
    {
        $request = Request::create(self::URI, 'POST', $post);
        $request->setSession($this->session);

        return $request;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function state(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'ics',
            'file' => 'system/tmp/club.ics',
            'startDate' => '2026-01-01',
            'endDate' => '',
            'timeshift' => 0,
            'deleteCalendar' => false,
            'filterEventTitle' => '',
            'mode' => 'missing',
            'fileTimezone' => '',
        ];
    }

    private function call(Request $request, int $id = 3): RedirectResponse
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker
            ->method('isGranted')
            ->willReturnCallback(fn (string $attribute): bool => !($this->denied[$attribute] ?? false))
        ;

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router
            ->method('generate')
            ->willReturn('/back')
        ;

        $csrf = $this->createStub(ContaoCsrfTokenManager::class);

        $controller = new class($this->dataContainer, $this->icsImporter, $this->csvImport, $csrf, $router, new NullLogger()) extends ImportBackendController {
            protected function findCalendar(int $id): CalendarModel|null
            {
                return 3 === $id ? CalendarModel::findById($id) : null;
            }

            protected function loadLanguageFiles(): void
            {
            }
        };
        $controller->setContainer(new ServiceLocator(['security.authorization_checker' => static fn (): AuthorizationCheckerInterface => $checker]));

        $response = $controller($request, $id);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        return $response;
    }
}
