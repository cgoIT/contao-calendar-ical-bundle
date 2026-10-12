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

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Registry;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Pc;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;
use Psr\Log\LoggerInterface;

/**
 * Covers the parts of the importer that do not need a database. The importer's constructor
 * initializes the Contao framework, so the instance is created without it.
 */
final class IcsImportTest extends ContaoTestCase
{
    private string $previousTimezone;

    private IcsImport $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();

        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        $reflection = new \ReflectionClass(IcsImport::class);
        $this->import = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('maxRepeatCount')->setValue($this->import, 365);
        $reflection->getProperty('arrMonths')->setValue($this->import, ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);

        parent::tearDown();
    }

    public function testGetDateFromPcUsesTzidParameter(): void
    {
        $vevent = new Vevent();
        $vevent->setDtstart(new \DateTime('2024-05-01 18:00', new \DateTimeZone('America/New_York')), [IcalInterface::TZID => 'America/New_York']);

        [$date, $timezone] = $this->import->getDateFromPc($vevent->getDtstart(true), 'Europe/Berlin');

        $this->assertSame('America/New_York', $timezone);
        $this->assertSame('2024-05-01 18:00', $date->format('Y-m-d H:i'));
    }

    public function testGetDateFromPcUsesTimezoneOfTheDate(): void
    {
        $vevent = new Vevent();
        $vevent->setDtstart(new \DateTime('2024-05-01 18:00', new \DateTimeZone('Asia/Tokyo')));

        [$date, $timezone] = $this->import->getDateFromPc($vevent->getDtstart(true), 'Europe/Berlin');

        $this->assertSame('Asia/Tokyo', $timezone);
        $this->assertSame('2024-05-01 18:00', $date->format('Y-m-d H:i'));
    }

    public function testGetDateFromPcReadsAllDayDateFromParsedIcs(): void
    {
        $cal = new Vcalendar();
        $cal->parse("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//test//EN\r\nBEGIN:VEVENT\r\nUID:1\r\nDTSTAMP:20240101T000000Z\r\nDTSTART;VALUE=DATE:20240501\r\nDTEND;VALUE=DATE:20240502\r\nSUMMARY:Day\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n");
        $vevent = $cal->getComponent(1);
        $this->assertInstanceOf(Vevent::class, $vevent);
        $pc = $vevent->getDtstart(true);
        $this->assertInstanceOf(Pc::class, $pc);

        [$date] = $this->import->getDateFromPc($pc, 'Europe/Berlin');

        $this->assertSame('2024-05-01', $date->format('Y-m-d'));
    }

    public function testRecurringUntilDateIsInterpretedInEventTimezone(): void
    {
        $until = new \DateTime('2024-06-01 12:00:00', new \DateTimeZone('UTC'));

        $result = $this->call('getRecurringUntilDate', [IcalInterface::UNTIL => $until], 'Europe/Berlin', 0, strtotime('2024-05-01'));

        $this->assertSame((new \DateTime('2024-06-01 12:00:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp(), $result);
    }

    public function testRecurringUntilDateAppliesTimeshift(): void
    {
        $until = new \DateTime('2024-06-01 12:00:00');

        $plain = $this->call('getRecurringUntilDate', [IcalInterface::UNTIL => $until], 'Europe/Berlin', 0, 0);
        $shifted = $this->call('getRecurringUntilDate', [IcalInterface::UNTIL => $until], 'Europe/Berlin', 2, 0);

        $this->assertSame($plain + 7200, $shifted);
    }

    public function testRecurringUntilDateBeforeEventStartIsInvalid(): void
    {
        $until = new \DateTime('2024-04-01 12:00:00');

        $this->assertFalse($this->call('getRecurringUntilDate', [IcalInterface::UNTIL => $until], 'Europe/Berlin', 0, strtotime('2024-05-01')));
    }

    public function testRecurringUntilDateIsNullWithoutUntil(): void
    {
        $this->assertNull($this->call('getRecurringUntilDate', [IcalInterface::FREQ => 'DAILY'], 'Europe/Berlin', 0, 0));
    }

    public function testRecurrenceCountForWeeklyEventUntilEnd(): void
    {
        $event = $this->event([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'startDate' => strtotime('2024-05-01'),
            'repeatEnd' => strtotime('2024-05-29 23:59:59'),
        ]);

        // recurrences on 8th, 15th, 22nd and 29th of May
        $this->assertSame(4, $this->call('calculateRecurrenceCount', $event));
    }

    public function testRecurrenceCountIsZeroWithoutRepeatEnd(): void
    {
        $event = $this->event([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'startDate' => strtotime('2024-05-01'),
            'repeatEnd' => 0,
        ]);

        $this->assertSame(0, $this->call('calculateRecurrenceCount', $event));
    }

    public function testRecurrenceCountSkipsWeekendsWhenHidden(): void
    {
        $event = $this->event([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'days', 'value' => 1]),
            'hideOnWeekend' => '1',
            'startDate' => strtotime('2024-05-03'), // Friday
            'repeatEnd' => strtotime('2024-05-08 23:59:59'),
        ]);

        // Sat 4th and Sun 5th are skipped: Mon 6th, Tue 7th, Wed 8th
        $this->assertSame(3, $this->call('calculateRecurrenceCount', $event));
    }

    public function testRecurrenceCountIsCappedAtConfiguredMaximum(): void
    {
        $reflection = new \ReflectionClass(IcsImport::class);
        $import = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('maxRepeatCount')->setValue($import, 10);

        $event = $this->event([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'days', 'value' => 1]),
            'startDate' => strtotime('2024-01-01'),
            'repeatEnd' => strtotime('2025-12-31'),
        ]);

        $method = new \ReflectionMethod(IcsImport::class, 'calculateRecurrenceCount');

        $this->assertSame(10, $method->invoke($import, $event));
    }

    public function testRecurrenceEndingBeforeFirstRepetitionMakesEventNonRecurring(): void
    {
        $event = $this->event([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'startDate' => strtotime('2024-05-01'),
            'repeatEnd' => strtotime('2024-05-03'),
        ]);

        $this->assertSame(0, $this->call('calculateRecurrenceCount', $event));
        $this->assertFalse($event->recurring);
    }

    public function testExtendedRecurrenceCountForFirstThursdayOfMonth(): void
    {
        // First Thursday: 2024-05-02 (the event), 06-06, 07-04, 08-01 are within the range, 09-05 is after the end
        $event = $this->extendedEvent('2024-05-02', '2024-08-31');

        $this->assertSame(3, $this->call('calculateRecurrenceCount', $event));
    }

    public function testExtendedRecurrenceCountIncludesTheLastDay(): void
    {
        $event = $this->extendedEvent('2024-05-02', '2024-08-01');

        $this->assertSame(3, $this->call('calculateRecurrenceCount', $event));
    }

    public function testExtendedRecurrenceCountWithoutRecurrenceInRange(): void
    {
        $event = $this->extendedEvent('2024-05-02', '2024-06-05');

        $this->assertSame(0, $this->call('calculateRecurrenceCount', $event));
    }

    public function testExtendedRecurrenceCountCountsDateLaterInTheStartMonth(): void
    {
        // The event starts on a Wednesday, the first Thursday of the month follows two days later
        $event = $this->extendedEvent('2024-05-01', '2024-06-30');

        $this->assertSame(2, $this->call('calculateRecurrenceCount', $event));
    }

    private function extendedEvent(string $start, string $repeatEnd): CalendarEventsModel
    {
        return $this->event([
            'addTime' => '1',
            'recurringExt' => '1',
            'repeatEachExt' => serialize(['unit' => 'thursday', 'value' => 'first']),
            'startDate' => strtotime($start),
            'startTime' => strtotime($start.' 18:00'),
            'endDate' => strtotime($start),
            'endTime' => strtotime($start.' 19:00'),
            'repeatEnd' => strtotime($repeatEnd.' 23:59:59'),
        ]);
    }

    public function testRecurrenceCountIsZeroForNonRecurringEvent(): void
    {
        $event = $this->event(['startDate' => strtotime('2024-05-01'), 'repeatEnd' => strtotime('2024-06-01')]);

        $this->assertSame(0, $this->call('calculateRecurrenceCount', $event));
    }

    public function testDateValueIsReadAsMidnightInTheCalendarTimezone(): void
    {
        foreach (['UTC', 'Europe/Berlin', 'America/New_York'] as $phpTimezone) {
            date_default_timezone_set($phpTimezone);

            [$date, $timezone] = $this->import->getDateFromPc($this->dtstart('DTSTART;VALUE=DATE:20240501'), 'America/New_York');

            $this->assertSame('America/New_York', $timezone);
            $this->assertSame('2024-05-01 00:00', $date->format('Y-m-d H:i'));
            $this->assertSame((new \DateTime('2024-05-01 00:00', new \DateTimeZone('America/New_York')))->getTimestamp(), $date->getTimestamp());
        }
    }

    public function testFloatingTimeIsReadInTheCalendarTimezone(): void
    {
        [$date, $timezone] = $this->import->getDateFromPc($this->dtstart('DTSTART:20240501T100000'), 'Europe/Berlin');

        $this->assertSame('Europe/Berlin', $timezone);
        $this->assertSame((new \DateTime('2024-05-01 10:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp(), $date->getTimestamp());
    }

    public function testUtcTimeStaysUtc(): void
    {
        [$date] = $this->import->getDateFromPc($this->dtstart('DTSTART:20240501T100000Z'), 'America/New_York');

        $this->assertSame((new \DateTime('2024-05-01 10:00', new \DateTimeZone('UTC')))->getTimestamp(), $date->getTimestamp());
    }

    public function testRecurrenceCountIsKnownBeforeTheRepeatEndIsCalculated(): void
    {
        // A new event has no "recurrences" yet; COUNT=4 means 3 additional occurrences
        $event = $this->event([
            'startDate' => strtotime('2024-05-01'),
            'startTime' => strtotime('2024-05-01'),
            'endTime' => strtotime('2024-05-01 23:59:59'),
            'recurring' => true,
        ]);

        $this->call('applyRecurrenceEnd', $event, ['FREQ' => 'WEEKLY', 'COUNT' => 4], ['unit' => 'weeks', 'value' => 1], 'Europe/Berlin', 0, false);

        $this->assertSame(3, $event->recurrences);
        $this->assertSame(strtotime('+ 3 weeks', strtotime('2024-05-01 23:59:59')), $event->repeatEnd);
    }

    public function testRecurrenceWithoutCountAndUntilIsUnlimited(): void
    {
        $event = $this->event(['startDate' => strtotime('2024-05-01'), 'endTime' => strtotime('2024-05-01 23:59:59'), 'recurring' => true]);

        $this->call('applyRecurrenceEnd', $event, ['FREQ' => 'WEEKLY'], ['unit' => 'weeks', 'value' => 1], 'Europe/Berlin', 0, false);

        $this->assertSame(0, $event->recurrences);
        $this->assertSame(4_294_967_295, $event->repeatEnd);
    }

    public function testRecurrenceWithUntilStoresTheEndAndTheCount(): void
    {
        $event = $this->event([
            'startDate' => strtotime('2024-05-01'),
            'startTime' => strtotime('2024-05-01 18:00'),
            'endTime' => strtotime('2024-05-01 19:00'),
            'recurring' => true,
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
        ]);

        $this->call('applyRecurrenceEnd', $event, ['FREQ' => 'WEEKLY', 'UNTIL' => new \DateTime('2024-05-29 18:00', new \DateTimeZone('UTC'))], ['unit' => 'weeks', 'value' => 1], 'Europe/Berlin', 0, false);

        $this->assertSame((new \DateTime('2024-05-29 18:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp(), $event->repeatEnd);
        $this->assertGreaterThan(0, $event->recurrences);
    }

    public function testRecurrenceEndingBeforeTheStartIsNotRecurring(): void
    {
        $event = $this->event(['startDate' => strtotime('2024-05-01'), 'endTime' => strtotime('2024-05-01 23:59:59'), 'recurring' => true]);

        $this->call('applyRecurrenceEnd', $event, ['FREQ' => 'WEEKLY', 'UNTIL' => new \DateTime('2024-04-01', new \DateTimeZone('UTC'))], ['unit' => 'weeks', 'value' => 1], 'Europe/Berlin', 0, false);

        $this->assertFalse($event->recurring);
        $this->assertSame(0, $event->repeatEnd);
    }

    public function testRepeatEndOfWeekdayRulesDoesNotDependOnTheBackendLanguage(): void
    {
        $GLOBALS['TL_LANG']['MONTHS'] = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        $event = $this->event([
            'startDate' => strtotime('2024-05-02'),
            'startTime' => strtotime('2024-05-02 18:00'),
            'endTime' => strtotime('2024-05-02 19:00'),
            'recurrences' => -1,
        ]);

        $repeatEnd = $this->call('getRepeatEnd', $event, ['FREQ' => 'MONTHLY'], ['unit' => 'thursday', 'value' => 'first'], 'Europe/Berlin', 0, true);

        $this->assertGreaterThan(strtotime('2024-05-02'), $repeatEnd);
    }

    private function dtstart(string $line): Pc
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:x\r\nBEGIN:VEVENT\r\nUID:1\r\nDTSTAMP:20240101T000000Z\r\n".$line."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $calendar = new Vcalendar();
        $calendar->parse($ics);

        return $calendar->getComponent(IcalInterface::VEVENT)->getDtstart(true);
    }

    public function testAllDayEndIsTheEndOfTheLastDay(): void
    {
        // DTEND of a DATE value is exclusive: 2024-05-01 until 2024-05-03 ends on 2024-05-04
        $end = new \DateTime('2024-05-04 00:00', new \DateTimeZone('Europe/Berlin'));

        [$endDate, $endTime] = $this->import->getEndTimestamps($end, false);

        $this->assertSame((new \DateTime('2024-05-03 00:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp(), $endDate);
        $this->assertSame((new \DateTime('2024-05-03 23:59:59', new \DateTimeZone('Europe/Berlin')))->getTimestamp(), $endTime);
    }

    public function testEndWithTimeIsTakenOver(): void
    {
        $end = new \DateTime('2024-05-01 19:00', new \DateTimeZone('Europe/Berlin'));

        $this->assertSame([$end->getTimestamp(), $end->getTimestamp()], $this->import->getEndTimestamps($end, true));
    }

    public function testAllDayEventsKeepTheirEndWhenImportedAndExportedAgain(): void
    {
        $tz = new \DateTimeZone('Europe/Berlin');
        [$endDate, $endTime] = $this->import->getEndTimestamps(new \DateTime('2024-05-04', $tz), false);

        $parser = $this->createStub(InsertTagParser::class);
        $parser->method('replaceInline')->willReturnArgument(0);

        $calendar = new CalendarModel();
        $calendar->id = 1;
        Registry::getInstance()->register($calendar);

        $event = $this->event([
            'id' => 5,
            'pid' => 1,
            'title' => 'Camp',
            'addTime' => '',
            'startDate' => (new \DateTime('2024-05-01', $tz))->getTimestamp(),
            'startTime' => (new \DateTime('2024-05-01', $tz))->getTimestamp(),
            'endDate' => $endDate,
            'endTime' => $endTime,
        ]);

        $ics = (new IcsExport($parser))->exportEvent($event)->createCalendar();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20240501', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20240504', $ics);
    }

    public function testImportedTextIsEncodedAndInsertTagsAreRemoved(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Fish &amp; Chips',
            $this->call('encodeImportedText', '<script>alert("x")</script>{{file::1}} Fish & Chips'),
        );
        $this->assertSame('Tom &amp; Jerry', $this->call('encodeImportedText', 'Tom &amp; Jerry'));
    }

    public function testRewriteTitleAppliesPattern(): void
    {
        $this->assertSame('Spiel: Training', $this->call('rewriteTitle', 'Training', '/^/', 'Spiel: '));
        $this->assertSame('Training', $this->call('rewriteTitle', 'Training', null, null));
    }

    public function testRewriteTitleKeepsTitleForInvalidPattern(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $this->bootContao()->set('monolog.logger.contao.general', $logger);

        $this->assertSame('Training', $this->call('rewriteTitle', 'Training', '/(unclosed/', 'x'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function event(array $data): CalendarEventsModel
    {
        $event = new CalendarEventsModel();

        foreach ($data as $key => $value) {
            $event->{$key} = $value;
        }

        return $event;
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(IcsImport::class, $method))->invoke($this->import, ...$arguments);
    }
}
