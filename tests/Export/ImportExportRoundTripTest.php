<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Export;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Tests\DatabaseTestCase;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Kigkonsult\Icalcreator\Vcalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Imports a feed into the database and exports it again: the events have to be the same as in the feed.
 */
final class ImportExportRoundTripTest extends DatabaseTestCase
{
    /**
     * @param list<string> $lines    The lines of the imported event
     * @param list<string> $expected The lines the exported event has to contain
     */
    #[DataProvider('provideEvents')]
    public function testExportsWhatWasImported(array $lines, array $expected): void
    {
        $this->importIcs($this->vevent('round-trip', ...$lines));

        $ics = $this->export();

        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));

        foreach ($expected as $line) {
            $this->assertStringContainsString($line."\r\n", $ics);
        }
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function provideEvents(): iterable
    {
        yield 'time in UTC' => [
            ['DTSTART:20240501T160000Z', 'DTEND:20240501T180000Z', 'SUMMARY:Training'],
            ['DTSTART;TZID=Europe/Berlin:20240501T180000', 'DTEND;TZID=Europe/Berlin:20240501T200000', 'SUMMARY:Training'],
        ];

        yield 'time in winter time' => [
            ['DTSTART:20240115T170000Z', 'DTEND:20240115T180000Z', 'SUMMARY:Winter'],
            ['DTSTART;TZID=Europe/Berlin:20240115T180000', 'DTEND;TZID=Europe/Berlin:20240115T190000'],
        ];

        yield 'time with a time zone of the feed' => [
            ['DTSTART;TZID=America/New_York:20240501T120000', 'DTEND;TZID=America/New_York:20240501T130000', 'SUMMARY:Call'],
            ['DTSTART;TZID=Europe/Berlin:20240501T180000', 'DTEND;TZID=Europe/Berlin:20240501T190000'],
        ];

        yield 'time without time zone (floating)' => [
            ['DTSTART:20240501T180000', 'DTEND:20240501T200000', 'SUMMARY:Lokal'],
            ['DTSTART;TZID=Europe/Berlin:20240501T180000', 'DTEND;TZID=Europe/Berlin:20240501T200000'],
        ];

        yield 'event ending after midnight' => [
            ['DTSTART:20240330T210000Z', 'DTEND:20240330T230000Z', 'SUMMARY:Nacht'],
            ['DTSTART;TZID=Europe/Berlin:20240330T220000', 'DTEND;TZID=Europe/Berlin:20240331T000000'],
        ];

        yield 'event during the change to the summer time' => [
            ['DTSTART:20240331T003000Z', 'DTEND:20240331T013000Z', 'SUMMARY:Umstellung'],
            ['DTSTART;TZID=Europe/Berlin:20240331T013000', 'DTEND;TZID=Europe/Berlin:20240331T033000'],
        ];

        yield 'event across midnight' => [
            ['DTSTART:20240501T210000Z', 'DTEND:20240501T230000Z', 'SUMMARY:Spaet'],
            ['DTSTART;TZID=Europe/Berlin:20240501T230000', 'DTEND;TZID=Europe/Berlin:20240502T010000'],
        ];

        yield 'single all day event' => [
            ['DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240502', 'SUMMARY:Feiertag'],
            ['DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240502'],
        ];

        yield 'all day event of several days' => [
            ['DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240504', 'SUMMARY:Ausflug'],
            ['DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240504'],
        ];

        yield 'all day event over the end of the month' => [
            ['DTSTART;VALUE=DATE:20240530', 'DTEND;VALUE=DATE:20240603', 'SUMMARY:Pfingstlager'],
            ['DTSTART;VALUE=DATE:20240530', 'DTEND;VALUE=DATE:20240603'],
        ];

        yield 'all day event on the day of the change to the summer time' => [
            ['DTSTART;VALUE=DATE:20240331', 'DTEND;VALUE=DATE:20240401', 'SUMMARY:Umstellungstag'],
            ['DTSTART;VALUE=DATE:20240331', 'DTEND;VALUE=DATE:20240401'],
        ];

        yield 'weekly recurrence with count' => [
            ['DTSTART:20240501T160000Z', 'DTEND:20240501T180000Z', 'SUMMARY:Stammtisch', 'RRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4'],
            ['DTSTART;TZID=Europe/Berlin:20240501T180000', 'RRULE:FREQ=WEEKLY;COUNT=4;INTERVAL=2'],
        ];

        yield 'daily recurrence' => [
            ['DTSTART:20240501T160000Z', 'DTEND:20240501T170000Z', 'SUMMARY:Taeglich', 'RRULE:FREQ=DAILY;COUNT=3'],
            ['RRULE:FREQ=DAILY;COUNT=3'],
        ];

        yield 'monthly recurrence' => [
            ['DTSTART:20240501T160000Z', 'DTEND:20240501T170000Z', 'SUMMARY:Monatlich', 'RRULE:FREQ=MONTHLY;COUNT=5'],
            ['RRULE:FREQ=MONTHLY;COUNT=5'],
        ];

        yield 'recurrence of an all day event' => [
            ['DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240502', 'SUMMARY:Jahrestag', 'RRULE:FREQ=YEARLY;COUNT=2'],
            ['DTSTART;VALUE=DATE:20240501', 'DTEND;VALUE=DATE:20240502', 'RRULE:FREQ=YEARLY;COUNT=2'],
        ];

        yield 'recurrence until a date' => [
            ['DTSTART:20240501T160000Z', 'DTEND:20240501T170000Z', 'SUMMARY:Bis', 'RRULE:FREQ=WEEKLY;UNTIL=20240529T160000Z'],
            ['RRULE:FREQ=WEEKLY;COUNT=5'],
        ];
    }

    public function testExportsTheTextsOfTheFeed(): void
    {
        $this->importIcs($this->vevent('texts', 'DTSTART:20240501T160000Z', 'DTEND:20240501T170000Z', 'SUMMARY:Sommerfest & Grillen <b>fett</b>'));

        $ics = $this->export();

        $this->assertStringContainsString('SUMMARY:Sommerfest & Grillen <b>fett</b>', $ics, 'the title is stored encoded and exported as text again');
    }

    public function testImportsTheSameFeedAgainWithoutChangingTheExport(): void
    {
        $feed = $this->vevent('again', 'DTSTART:20240501T160000Z', 'DTEND:20240501T180000Z', 'SUMMARY:Immer gleich', 'RRULE:FREQ=WEEKLY;COUNT=3');

        $this->importIcs($feed);
        $first = $this->normalize($this->export());
        $this->importIcs($feed);
        $second = $this->normalize($this->export());

        $this->assertSame($first, $second);
    }

    public function testKeepsSeveralEventsApart(): void
    {
        $this->importIcs(
            $this->vevent('a', 'DTSTART:20240501T160000Z', 'DTEND:20240501T170000Z', 'SUMMARY:Eins')
            .$this->vevent('b', 'DTSTART;VALUE=DATE:20240502', 'DTEND;VALUE=DATE:20240503', 'SUMMARY:Zwei'),
        );

        $ics = $this->export();

        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString("SUMMARY:Eins\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:Zwei\r\n", $ics);
    }

    private function export(): string
    {
        $parser = $this->createStub(InsertTagParser::class);
        $parser
            ->method('replaceInline')
            ->willReturnArgument(0)
        ;

        $export = new IcsExport($parser, new EventDispatcher());
        $ical = $export->getVcalendar([CalendarModel::findById(1)], mktime(0, 0, 0, 1, 1, 2024), mktime(0, 0, 0, 1, 1, 2025));

        $this->assertInstanceOf(Vcalendar::class, $ical);

        // Long lines are folded
        return str_replace("\r\n ", '', $ical->createCalendar());
    }

    /**
     * Without the time stamps and the generated identifiers, which differ from run to run.
     */
    private function normalize(string $ics): string
    {
        return (string) preg_replace('/^(DTSTAMP|UID|CREATED|LAST-MODIFIED):.*\r\n/m', '', $ics);
    }
}
