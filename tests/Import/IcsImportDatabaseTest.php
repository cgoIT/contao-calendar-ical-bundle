<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Import;

use Cgoit\ContaoCalendarIcalBundle\Event\AfterImportItemEvent;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Import\RemoteIcsDownloader;
use Cgoit\ContaoCalendarIcalBundle\Tests\DatabaseTestCase;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Kigkonsult\Icalcreator\Vcalendar;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Runs the import of the events against a real (SQLite) database, so that the models are saved and read back.
 */
final class IcsImportDatabaseTest extends DatabaseTestCase
{
    public function testImportsATimedEvent(): void
    {
        $this->importIcs($this->vevent('1', 'DTSTART:20240501T180000Z', 'DTEND:20240501T200000Z', 'SUMMARY:Sommerfest', 'DESCRIPTION:Mit <b>Musik</b>', 'LOCATION:Marktplatz'));

        $event = $this->events()[0];

        $this->assertSame('Sommerfest', $event['title']);
        $this->assertSame('sommerfest', $event['alias']);
        $this->assertSame('1', $event['ical_uuid']);
        $this->assertSame(1, (int) $event['pid']);
        $this->assertSame(7, (int) $event['author']);
        $this->assertSame('1', $event['addTime']);
        $this->assertSame('1', $event['published']);
        $this->assertSame(gmmktime(18, 0, 0, 5, 1, 2024), (int) $event['startTime']);
        $this->assertSame(gmmktime(20, 0, 0, 5, 1, 2024), (int) $event['endTime']);

        // Description and location end up as a text element, HTML is encoded
        $texts = $this->db->fetchFirstColumn("SELECT text FROM tl_content WHERE ptable = 'tl_calendar_events' AND pid = ".(int) $event['id']);
        $this->assertCount(2, $texts);
        $this->assertStringContainsString('&lt;b&gt;Musik&lt;/b&gt;', (string) $texts[0]);
        $this->assertStringNotContainsString('<b>', (string) $texts[0]);
        $this->assertStringContainsString('Marktplatz', (string) $texts[1]);
    }

    public function testImportsAnAllDayEventWithAnExclusiveEnd(): void
    {
        $this->importIcs($this->vevent('2', 'DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240503', 'SUMMARY:Ausflug'));

        $event = $this->events()[0];

        $this->assertEmpty($event['addTime']);
        $this->assertSame(mktime(0, 0, 0, 5, 1, 2024), (int) $event['startDate']);
        // The last day is the day before DTEND
        $this->assertSame(mktime(0, 0, 0, 5, 2, 2024), (int) $event['endDate']);
    }

    public function testImportsARecurringEvent(): void
    {
        $this->importIcs($this->vevent('3', 'DTSTART:20240501T180000Z', 'DTEND:20240501T200000Z', 'SUMMARY:Stammtisch', 'RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4'));

        $event = $this->events()[0];

        $this->assertSame('1', $event['recurring']);
        $this->assertSame(['unit' => 'weeks', 'value' => '2'], unserialize($event['repeatEach']));
        $this->assertSame(3, (int) $event['recurrences']);
        $this->assertSame(gmmktime(20, 0, 0, 6, 12, 2024), (int) $event['repeatEnd']);
    }

    public function testUpdatesAnEventOnTheNextImportInsteadOfDuplicatingIt(): void
    {
        $this->importIcs($this->vevent('4', 'DTSTART:20240501T180000Z', 'SUMMARY:Alt'));
        $this->importIcs($this->vevent('4', 'DTSTART:20240502T180000Z', 'SUMMARY:Neu'));

        $events = $this->events();

        $this->assertCount(1, $events);
        $this->assertSame('Neu', $events[0]['title']);
        $this->assertSame(gmmktime(18, 0, 0, 5, 2, 2024), (int) $events[0]['startTime']);
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_content WHERE pid = '.(int) $events[0]['id']));
    }

    public function testRemovesEventsThatAreNotInTheFeedAnymoreIfTheCalendarIsReplaced(): void
    {
        $this->importIcs($this->vevent('5', 'DTSTART:20240501T180000Z', 'SUMMARY:Bleibt').$this->vevent('6', 'DTSTART:20240502T180000Z', 'SUMMARY:Entfaellt'));
        $this->importIcs($this->vevent('5', 'DTSTART:20240501T180000Z', 'SUMMARY:Bleibt'), deleteCalendar: true);

        $this->assertSame(['Bleibt'], array_column($this->events(), 'title'));
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_content'), 'the text element of the removed event is gone');
    }

    public function testKeepsEachTextWhenAnEventIsImportedAgain(): void
    {
        $feed = $this->vevent('13', 'DTSTART:20240501T180000Z', 'SUMMARY:Fest', 'DESCRIPTION:Beschreibung', 'LOCATION:Park');

        $this->importIcs($feed);
        $this->importIcs($feed);

        $this->assertSame(2, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_content'));
    }

    public function testKeepsAllEventsIfTheCalendarIsNotReplaced(): void
    {
        $this->importIcs($this->vevent('5', 'DTSTART:20240501T180000Z', 'SUMMARY:Eins'));
        $this->importIcs($this->vevent('6', 'DTSTART:20240502T180000Z', 'SUMMARY:Zwei'));

        $this->assertCount(2, $this->events());
    }

    public function testCreatesUnpublishedEventsIfTheCalendarDoesNotPublishThem(): void
    {
        $this->db->executeStatement("UPDATE tl_calendar SET ical_publish_imported = '0' WHERE id = 1");

        $this->importIcs($this->vevent('7', 'DTSTART:20240501T180000Z', 'SUMMARY:Entwurf'));

        $this->assertEmpty($this->events()[0]['published']);
    }

    public function testFiltersAndRewritesTheTitle(): void
    {
        $this->importIcs(
            $this->vevent('8', 'DTSTART:20240501T180000Z', 'SUMMARY:[Verein] Training').$this->vevent('9', 'DTSTART:20240502T180000Z', 'SUMMARY:Sonstiges'),
            filter: 'training',
            pattern: '/^\[Verein\] /',
            replacement: 'Club: ',
        );

        $this->assertSame(['Club: Training'], array_column($this->events(), 'title'));
    }

    public function testRemovesTheMatchOfThePatternIfThereIsNoReplacement(): void
    {
        $this->importIcs($this->vevent('8', 'DTSTART:20240501T180000Z', 'SUMMARY:[Verein] Training'), pattern: '/^\\[Verein\\] /');

        $this->assertSame(['Training'], array_column($this->events(), 'title'));
    }

    public function testKeepsTheTitleIfThereIsNoPattern(): void
    {
        $this->importIcs($this->vevent('8', 'DTSTART:20240501T180000Z', 'SUMMARY:[Verein] Training'), replacement: 'X');

        $this->assertSame(['[Verein] Training'], array_column($this->events(), 'title'));
    }

    public function testSkipsEventsOutsideThePeriod(): void
    {
        $this->importIcs($this->vevent('10', 'DTSTART:20250601T180000Z', 'SUMMARY:Naechstes Jahr'));

        $this->assertSame([], $this->events());
    }

    public function testLeavesTheDatabaseUnchangedIfTheImportFails(): void
    {
        $this->importIcs($this->vevent('11', 'DTSTART:20240501T180000Z', 'SUMMARY:Vorhanden'));

        $dispatcher = new EventDispatcher();
        $failing = false;
        $dispatcher->addListener(
            AfterImportItemEvent::class,
            static function () use (&$failing): void {
                $failing = true;

                throw new \RuntimeException('boom');
            },
        );
        $import = $this->createImport($dispatcher);

        try {
            $this->runImport($import, $this->vevent('12', 'DTSTART:20240502T180000Z', 'SUMMARY:Neu'), true);
            $this->fail('the exception is expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertTrue($failing);
        $this->assertSame(['Vorhanden'], array_column($this->events(), 'title'), 'the new event is rolled back, the old one is not removed');
    }

    private function vevent(string $uid, string ...$lines): string
    {
        return "BEGIN:VEVENT\r\nUID:".$uid."\r\nDTSTAMP:20240101T000000Z\r\n".implode("\r\n", $lines)."\r\nEND:VEVENT\r\n";
    }

    private function importIcs(string $events, bool $deleteCalendar = false, string|null $filter = null, string|null $pattern = null, string|null $replacement = null): void
    {
        $this->runImport($this->createImport(), $events, $deleteCalendar, $filter, $pattern, $replacement);
    }

    private function createImport(EventDispatcher|null $dispatcher = null): IcsImport
    {
        $pageAdapter = $this->createMock(Adapter::class);
        $pageAdapter
            ->method('__call')
            ->willReturn(null)
        ;

        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturn($pageAdapter)
        ;

        return new IcsImport(
            $this->db,
            $this->createSlug(),
            $framework,
            365,
            $dispatcher ?? new EventDispatcher(),
            $this->createMock(RemoteIcsDownloader::class),
            new NullLogger(),
            new ParameterBag(['cgoit_contao_calendar_ical.max_repeat_count' => 365]),
        );
    }

    private function runImport(IcsImport $import, string $events, bool $deleteCalendar = false, string|null $filter = null, string|null $pattern = null, string|null $replacement = null): void
    {
        $cal = new Vcalendar();
        $cal->parse("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\n".$events."END:VCALENDAR\r\n");

        $import->importFromIcsFile(
            $cal,
            CalendarModel::findById(1),
            new Date(mktime(0, 0, 0, 1, 1, 2024), 'Y-m-d'),
            new Date(mktime(0, 0, 0, 12, 31, 2024), 'Y-m-d'),
            ['Europe/Berlin', 'Europe/Berlin'],
            $filter,
            $pattern,
            $replacement,
            $deleteCalendar,
        );
    }
}
