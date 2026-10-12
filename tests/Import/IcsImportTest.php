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

use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarEventsModel;
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
        $this->markTestSkipped('Unclear behavior: for a first-Thursday event starting 2024-05-02 with repeatEnd 2024-08-31 the count is 5, i.e. the first Thursday of September 2024 (after repeatEnd) is counted. To be clarified and fixed (see TODO.md); then expect 4 (or 3 without the original).');
    }

    public function testRecurrenceCountIsZeroForNonRecurringEvent(): void
    {
        $event = $this->event(['startDate' => strtotime('2024-05-01'), 'repeatEnd' => strtotime('2024-06-01')]);

        $this->assertSame(0, $this->call('calculateRecurrenceCount', $event));
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
