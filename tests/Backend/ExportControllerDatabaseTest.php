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
use Cgoit\ContaoCalendarIcalBundle\Tests\DatabaseTestCase;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes the subscription files for calendars and events of a real (SQLite) database.
 */
final class ExportControllerDatabaseTest extends DatabaseTestCase
{
    private string $shareDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shareDir = $this->getTempDir().'/web/share';
        $this->db->executeStatement("UPDATE tl_calendar SET title = 'Verein &amp; Freunde', make_ical = '1', ical_alias = 'verein', ical_description = 'Termine &amp; mehr' WHERE id = 1");
        $this->db->executeStatement("INSERT INTO tl_calendar (id, title, make_ical) VALUES (2, 'Ohne Alias', '1')");
        $this->db->executeStatement("INSERT INTO tl_calendar (id, title, make_ical, protected) VALUES (3, 'Geschuetzt', '1', '1')");
        $this->db->executeStatement("INSERT INTO tl_calendar (id, title) VALUES (4, 'Ohne Abo')");

        $start = strtotime('+7 days 18:00');
        $this->addEvent(1, 'Training', $start);
        $this->addEvent(2, 'Spiel', $start);
        $this->addEvent(3, 'Intern', $start);
        $this->addEvent(4, 'Frei', $start);
        $this->addEvent(1, 'Entwurf', $start, published: false);
    }

    public function testWritesTheFileOfASingleCalendar(): void
    {
        $this->controller()->generateSubscriptions(CalendarModel::findById(1));

        $this->assertSame(['verein.ics'], $this->files());

        $ics = $this->read('verein.ics');
        $this->assertStringContainsString('SUMMARY:Training', $ics);
        $this->assertStringNotContainsString('Entwurf', $ics, 'unpublished events are not exported');
        $this->assertStringNotContainsString('Spiel', $ics);
        $this->assertStringContainsString('X-WR-CALNAME:Verein & Freunde', $ics, 'the entities of the calendar name are decoded');
        $this->assertStringContainsString('X-WR-CALDESC:Termine & mehr', $ics);
    }

    public function testWritesTheFileOfTheCalendarOfAnEvent(): void
    {
        $this->controller()->generateSubscriptions(CalendarEventsModel::findOneBy('title', 'Spiel'));

        $this->assertSame(['calendar2.ics'], $this->files());
        $this->assertStringContainsString('SUMMARY:Spiel', $this->read('calendar2.ics'));
    }

    public function testWritesTheFilesOfAllCalendarsButNotOfProtectedOnesOrOnesWithoutSubscription(): void
    {
        $this->controller()->generateSubscriptions();

        $this->assertSame(['calendar2.ics', 'verein.ics'], $this->files());
    }

    public function testReplacesTheOldFilesOfTheCalendarsButKeepsForeignFiles(): void
    {
        (new Filesystem())->dumpFile($this->shareDir.'/calendar1.ics', 'old');
        (new Filesystem())->dumpFile($this->shareDir.'/calendar3.ics', 'orphan');
        (new Filesystem())->dumpFile($this->shareDir.'/other.ics', 'foreign');

        $this->controller()->generateSubscriptions();

        $this->assertSame(['calendar2.ics', 'other.ics', 'verein.ics'], $this->files());
        $this->assertSame('foreign', $this->read('other.ics'));
    }

    public function testDoesNotWriteAFileWhenTheSubscriptionIsSwitchedOff(): void
    {
        $this->db->executeStatement("UPDATE tl_calendar SET make_ical = '' WHERE id = 1");
        (new Filesystem())->dumpFile($this->shareDir.'/verein.ics', 'old');

        $this->controller()->generateSubscriptions(CalendarModel::findById(1));

        $this->assertSame([], $this->files(), 'the old file is removed');
    }

    private function controller(): ExportController
    {
        $parser = $this->createStub(InsertTagParser::class);
        $parser
            ->method('replaceInline')
            ->willReturnArgument(0)
        ;

        return new ExportController(new IcsExport($parser, new EventDispatcher()), new NullLogger(), $this->getTempDir().'/web');
    }

    private function addEvent(int $calendar, string $title, int $start, bool $published = true): void
    {
        $this->db->insert('tl_calendar_events', [
            'pid' => $calendar,
            'tstamp' => time(),
            'title' => $title,
            'alias' => strtolower($title),
            'addTime' => '1',
            'startDate' => strtotime('midnight', $start),
            'startTime' => $start,
            'endDate' => strtotime('midnight', $start),
            'endTime' => $start + 3600,
            'published' => $published ? '1' : '',
        ]);
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        return is_dir($this->shareDir) ? array_values(array_diff(scandir($this->shareDir) ?: [], ['.', '..'])) : [];
    }

    private function read(string $file): string
    {
        return str_replace("\r\n ", '', (string) file_get_contents($this->shareDir.'/'.$file));
    }
}
