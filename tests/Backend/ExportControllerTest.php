<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Backend;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

final class ExportControllerTest extends ContaoTestCase
{
    private string $shareDir;

    protected function setUp(): void
    {
        parent::setUp();

        $projectDir = $this->getTempDir();
        $this->shareDir = $projectDir.'/public/share';

        $container = $this->bootContao([
            'contao.web_dir' => $projectDir.'/public',
            'kernel.project_dir' => $projectDir,
        ]);
        $container->set('monolog.logger.contao.general', new NullLogger());

        $filesystem = new Filesystem();

        foreach (['calendar1.ics', 'club.ics', 'calendar99.ics', 'other.ics', 'readme.txt'] as $file) {
            $filesystem->dumpFile($this->shareDir.'/'.$file, 'x');
        }

        $filesystem->mkdir($this->shareDir.'/calendar7.ics');
    }

    public function testSubscriptionFileNameUsesAliasOrCalendarId(): void
    {
        $controller = $this->controller();

        $this->assertSame('club', $controller->getSubscriptionFileName($this->calendar(1, 'club')));
        $this->assertSame('calendar2', $controller->getSubscriptionFileName($this->calendar(2, '')));
    }

    public function testRemovesOnlyFilesOfTheGivenCalendar(): void
    {
        $removed = $this->controller()->removeSubscriptions($this->calendar(1, 'club'));

        $this->assertEqualsCanonicalizing(['calendar1.ics', 'club.ics'], $removed);
        $this->assertFileDoesNotExist($this->shareDir.'/club.ics');
        $this->assertFileExists($this->shareDir.'/other.ics');
        $this->assertFileExists($this->shareDir.'/calendar99.ics');
    }

    public function testRemovesFilesOfAllCalendarsAndOrphansButNotForeignFiles(): void
    {
        $removed = $this->controller([$this->calendar(1, 'club')])->removeSubscriptions();

        $this->assertEqualsCanonicalizing(['calendar1.ics', 'club.ics', 'calendar99.ics'], $removed);
        $this->assertFileExists($this->shareDir.'/other.ics', 'Foreign files must not be deleted');
        $this->assertFileExists($this->shareDir.'/readme.txt');
        $this->assertDirectoryExists($this->shareDir.'/calendar7.ics', 'Directories must not be deleted');
    }

    public function testMissingShareDirectoryIsNoError(): void
    {
        (new Filesystem())->remove($this->shareDir);

        $this->assertSame([], $this->controller()->removeSubscriptions($this->calendar(1, '')));
    }

    private function calendar(int $id, string $alias): CalendarModel
    {
        $calendar = new CalendarModel();
        $calendar->id = $id;
        $calendar->ical_alias = $alias;

        return $calendar;
    }

    /**
     * @param array<CalendarModel> $calendars
     */
    private function controller(array $calendars = []): ExportController
    {
        $icsExport = new IcsExport($this->createStub(InsertTagParser::class), new EventDispatcher());

        return new class($icsExport, $calendars) extends ExportController {
            /**
             * @param array<CalendarModel> $calendars
             */
            public function __construct(
                IcsExport $icsExport,
                private readonly array $calendars,
            ) {
                parent::__construct($icsExport);
            }

            protected function findAllCalendars(): iterable
            {
                return $this->calendars;
            }
        };
    }
}
