<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Controller\Route;

use Cgoit\ContaoCalendarIcalBundle\Controller\Route\EventlistIcsExportController;
use Cgoit\ContaoCalendarIcalBundle\Export\EventlistModule;
use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Security\ExportAccessChecker;
use Cgoit\ContaoCalendarIcalBundle\Tests\DatabaseTestCase;
use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\PageModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Looks up the event list (module or content element) and its calendars in a real (SQLite) database.
 */
final class EventlistIcsExportControllerDatabaseTest extends DatabaseTestCase
{
    /**
     * @var list<list<int>>
     */
    private array $requestedCalendars = [];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_LANG']['MSC'] = ['cal_empty' => '', 'cal_emptyDay' => '', 'cal_emptyMonth' => '', 'cal_emptyYear' => ''];

        $this->db->executeStatement("CREATE TABLE tl_module (id INTEGER PRIMARY KEY, tstamp INTEGER NOT NULL DEFAULT 0, type TEXT, name TEXT, cal_calendar TEXT, cal_format TEXT, cal_featured TEXT, protected TEXT NOT NULL DEFAULT '', guests TEXT NOT NULL DEFAULT '', \"groups\" TEXT DEFAULT NULL)");
        $this->db->executeStatement('ALTER TABLE tl_content ADD COLUMN cal_calendar TEXT');
        $this->db->executeStatement('ALTER TABLE tl_content ADD COLUMN cal_format TEXT');
        $this->db->executeStatement('ALTER TABLE tl_content ADD COLUMN name TEXT');
        $this->db->executeStatement("ALTER TABLE tl_content ADD COLUMN invisible TEXT NOT NULL DEFAULT ''");
        $this->db->executeStatement("ALTER TABLE tl_content ADD COLUMN protected TEXT NOT NULL DEFAULT ''");
        $this->db->executeStatement("ALTER TABLE tl_content ADD COLUMN guests TEXT NOT NULL DEFAULT ''");
        $this->db->executeStatement('ALTER TABLE tl_content ADD COLUMN "groups" TEXT DEFAULT NULL');
        $this->db->executeStatement("ALTER TABLE tl_content ADD COLUMN start TEXT NOT NULL DEFAULT ''");
        $this->db->executeStatement("ALTER TABLE tl_content ADD COLUMN stop TEXT NOT NULL DEFAULT ''");

        $this->db->executeStatement("INSERT INTO tl_calendar (id, title) VALUES (2, 'Zweiter Kalender')");
        $this->db->executeStatement("INSERT INTO tl_calendar (id, title, protected) VALUES (3, 'Geschuetzt', '1')");
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['objPage'], $GLOBALS['TL_LANG']);

        parent::tearDown();
    }

    public function testExportsTheCalendarsOfAModule(): void
    {
        $this->module(5, 'eventlist', [1, 2], 'Vereinstermine');

        $response = $this->controller()(new Request(), '5');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame([[1, 2]], $this->requestedCalendars);
        $this->assertStringContainsString('Vereinstermine.ics', (string) $response->headers->get('Content-Disposition'));
    }

    public function testExportsTheCalendarsOfAContentElement(): void
    {
        $this->contentElement(6, 'eventlist', [2], 'Inhalt');

        $response = $this->controller()(new Request(), '6');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame([[2]], $this->requestedCalendars);
    }

    public function testTheModuleWinsIfTheIdIsOfAModuleAndAContentElement(): void
    {
        $this->module(7, 'eventlist', [1], 'Modul');
        $this->contentElement(7, 'eventlist', [2], 'Inhalt');

        $this->controller()(new Request(), '7');

        $this->assertSame([[1]], $this->requestedCalendars);
    }

    public function testFallsBackToTheContentElementIfTheModuleIsNoEventlist(): void
    {
        $this->module(8, 'html', [1], 'Kein Eventlist-Modul');
        $this->contentElement(8, 'eventlist', [2], 'Inhalt');

        $this->controller()(new Request(), '8');

        $this->assertSame([[2]], $this->requestedCalendars);
    }

    public function testElementsThatAreNoEventlistAreNotFound(): void
    {
        $this->module(9, 'html', [1], 'Kein Eventlist-Modul');
        $this->module(10, 'eventlist', [], 'Ohne Kalender');

        foreach (['9', '10', '404'] as $id) {
            try {
                $this->controller()(new Request(), $id);
                $this->fail('Not found is expected for '.$id);
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAnInvisibleContentElementIsNotFound(): void
    {
        $this->contentElement(11, 'eventlist', [1], 'Versteckt', ['invisible' => '1']);

        $this->expectException(NotFoundHttpException::class);

        $this->controller()(new Request(), '11');
    }

    public function testLeavesOutTheCalendarsTheVisitorMayNotSee(): void
    {
        $this->module(12, 'eventlist', [1, 3], 'Gemischt');

        $this->controller()(new Request(), '12');

        $this->assertSame([[1]], $this->requestedCalendars, 'the protected calendar is not exported');
    }

    public function testRespondsWithNoContentIfAllCalendarsAreProtected(): void
    {
        $this->module(13, 'eventlist', [3], 'Nur geschuetzt');

        $response = $this->controller()(new Request(), '13');

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame([], $this->requestedCalendars);
    }

    /**
     * @param list<int> $calendarIds
     *
     * @internal Called by the controller of the test
     */
    public function requestedCalendars(array $calendarIds): void
    {
        $this->requestedCalendars[] = $calendarIds;
    }

    /**
     * @param list<int> $calendars
     */
    private function module(int $id, string $type, array $calendars, string $name): void
    {
        $this->db->insert('tl_module', ['id' => $id, 'type' => $type, 'name' => $name, 'cal_calendar' => [] === $calendars ? null : serialize(array_map(strval(...), $calendars)), 'cal_format' => 'next_365']);
    }

    /**
     * @param list<int>             $calendars
     * @param array<string, string> $data
     */
    private function contentElement(int $id, string $type, array $calendars, string $name, array $data = []): void
    {
        $this->db->insert('tl_content', ['id' => $id, 'pid' => 0, 'ptable' => 'tl_news', 'type' => $type, 'name' => $name, 'cal_calendar' => serialize(array_map(strval(...), $calendars)), 'cal_format' => 'next_365'] + $data);
    }

    private function controller(): EventlistIcsExportController
    {
        $event = new CalendarEventsModel();
        $event->id = 100;
        $event->pid = 1;
        $event->title = 'Training';
        $event->addTime = false;
        $event->startDate = strtotime('+1 day midnight');
        $event->endDate = null;

        $parser = $this->createStub(InsertTagParser::class);
        $parser
            ->method('replaceInline')
            ->willReturnArgument(0)
        ;

        $test = $this;

        return new class($test, $event, new ResponseUtil(), new IcsExport($parser, new EventDispatcher()), $this->createStub(ContaoFramework::class), new ExportAccessChecker($this->createStub(Security::class))) extends EventlistIcsExportController {
            public function __construct(
                private readonly EventlistIcsExportControllerDatabaseTest $test,
                private readonly CalendarEventsModel $event,
                ResponseUtil $responseUtil,
                IcsExport $icsExport,
                ContaoFramework $framework,
                ExportAccessChecker $accessChecker,
            ) {
                parent::__construct($responseUtil, $icsExport, $framework, $accessChecker);
            }

            protected function findEvents(EventlistModule $module, array $calendarIds, int $start, int $end, bool|null $featured): array
            {
                $this->test->requestedCalendars($calendarIds);

                return [20240501 => [0 => [$this->event]]];
            }

            protected function findFallbackPage(Request $request): PageModel
            {
                return new PageModel();
            }

            protected function loadLanguageFiles(): void
            {
            }
        };
    }
}
