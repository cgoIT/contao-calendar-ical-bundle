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
use Contao\CalendarModel;
use Contao\Date;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

/**
 * Exceptions of recurring events (EXDATE, RECURRENCE-ID) and the setting to publish imported events.
 */
final class IcsImportExceptionsTest extends ContaoTestCase
{
    private const array FIELDS = ['useExceptions', 'repeatExceptions', 'exceptionList'];

    private string $previousTimezone;

    private IcsImport $import;

    /**
     * The components of a calendar are not usable once the calendar is destroyed.
     *
     * @var list<Vcalendar>
     */
    private array $calendars = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();

        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        $this->import = (new \ReflectionClass(IcsImport::class))->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);

        parent::tearDown();
    }

    public function testExdateInUtcIsAnExactPointInTime(): void
    {
        $event = $this->weeklyEvent();

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf(['EXDATE:20240508T160000Z']), 'Europe/Berlin', 0);

        $this->assertSame([$this->ts('2024-05-08 18:00')], array_keys($event->exceptionList));
    }

    public function testExdateWithTimezoneKeepsItsOwnTimezone(): void
    {
        $event = $this->weeklyEvent();

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf(['EXDATE;TZID=America/New_York:20240508T120000']), 'Europe/Berlin', 0);

        $this->assertSame([$this->ts('2024-05-08 18:00')], array_keys($event->exceptionList));
    }

    public function testExdateOfDatesAndFloatingTimesAreLocalTimes(): void
    {
        $event = $this->weeklyEvent();

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf(['EXDATE:20240508T180000', 'EXDATE;VALUE=DATE:20240515']), 'Europe/Berlin', 0);

        $this->assertSame([$this->ts('2024-05-08 18:00'), $this->ts('2024-05-15 00:00')], array_keys($event->exceptionList));
        $this->assertTrue((bool) $event->useExceptions);
    }

    public function testExdateWithSeveralValuesAndTimeshift(): void
    {
        $event = $this->weeklyEvent();

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf(['EXDATE;TZID=Europe/Berlin:20240508T180000,20240515T180000']), 'Europe/Berlin', 1);

        $this->assertSame([$this->ts('2024-05-08 19:00'), $this->ts('2024-05-15 19:00')], array_keys($event->exceptionList));
    }

    public function testNothingIsDoneWithoutTheFieldsOfTheCalendarExtendedBundle(): void
    {
        $event = $this->weeklyEvent();

        $this->call('handleRecurringExceptions', $event, [], $this->masterOf(['EXDATE:20240508T160000Z']), 'Europe/Berlin', 0);

        $this->assertNull($event->exceptionList);
    }

    public function testOverrideOnAnotherDayBecomesAMoveException(): void
    {
        $event = $this->weeklyEvent();
        $override = $this->override('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240509T180000', 'DTEND;TZID=Europe/Berlin:20240509T190000');

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf([]), 'Europe/Berlin', 0, [$override]);

        $this->assertSame(
            [['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'move', 'new_exception' => '+1 days']],
            $event->repeatExceptions,
        );
    }

    public function testOverrideWithOtherTimesGetsTheNewTimes(): void
    {
        $event = $this->weeklyEvent();
        $override = $this->override('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240507T100000', 'DTEND;TZID=Europe/Berlin:20240507T123000');

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf([]), 'Europe/Berlin', 0, [$override]);

        $this->assertSame(
            [['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'move', 'new_exception' => '-1 days', 'new_start' => '10:00', 'new_end' => '12:30']],
            $event->repeatExceptions,
        );
    }

    public function testOverrideOfAllDayEventIsMovedByDays(): void
    {
        $event = $this->event(['addTime' => false, 'startDate' => $this->ts('2024-05-01'), 'startTime' => $this->ts('2024-05-01'), 'endDate' => $this->ts('2024-05-01'), 'endTime' => $this->ts('2024-05-01 23:59:59')]);
        $override = $this->override('RECURRENCE-ID;VALUE=DATE:20240508', 'DTSTART;VALUE=DATE:20240510', 'DTEND;VALUE=DATE:20240511');

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf([]), 'Europe/Berlin', 0, [$override]);

        $this->assertSame(
            [['exception' => $this->ts('2024-05-08 00:00'), 'action' => 'move', 'new_exception' => '+2 days']],
            $event->repeatExceptions,
        );
    }

    public function testCancelledOverrideHidesTheRecurrence(): void
    {
        $event = $this->weeklyEvent();
        $override = $this->override('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240508T180000', 'STATUS:CANCELLED');

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf([]), 'Europe/Berlin', 0, [$override]);

        $this->assertSame([['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'hide']], $event->repeatExceptions);
    }

    public function testOverrideWithoutChangedDateAndTimeIsNoException(): void
    {
        $event = $this->weeklyEvent();
        $override = $this->override('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240508T180000', 'DTEND;TZID=Europe/Berlin:20240508T190000');

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf([]), 'Europe/Berlin', 0, [$override]);

        $this->assertFalse((bool) $event->useExceptions);
        $this->assertNull($event->repeatExceptions);
    }

    public function testOverridesAreFoldedIntoTheEventWithTheExceptionsOfTheCalendarExtendedBundle(): void
    {
        $cal = $this->calendar();
        $events = [$this->first($cal)];

        $items = $this->call('collectComponents', $cal, $events, $this->day('2024-05-01'), $this->day('2024-06-30'), 'Europe/Berlin', true);

        $this->assertSame(['series'], array_keys($items));
        $this->assertCount(1, $items['series'][1]);
    }

    public function testOverridesAreEventsOfTheirOwnWithoutTheExceptionsOfTheCalendarExtendedBundle(): void
    {
        $cal = $this->calendar();
        $events = [$this->first($cal)];

        $items = $this->call('collectComponents', $cal, $events, $this->day('2024-05-01'), $this->day('2024-06-30'), 'Europe/Berlin', false);

        $this->assertSame(['series', 'series#20240508T160000'], array_keys($items));
        $this->assertSame([], $items['series#20240508T160000'][1]);
    }

    public function testOverridesOutsideOfThePeriodAreNotImportedAsEventsOfTheirOwn(): void
    {
        $cal = $this->calendar();
        $events = [$this->first($cal)];

        $items = $this->call('collectComponents', $cal, $events, $this->day('2024-06-01'), $this->day('2024-06-30'), 'Europe/Berlin', false);

        $this->assertSame(['series'], array_keys($items));
    }

    public function testOverrideOfAnEventThatIsNotInTheFileIsAnEventOfItsOwn(): void
    {
        $cal = new Vcalendar();
        $cal->parse($this->ics(['BEGIN:VEVENT', 'UID:other', 'DTSTAMP:20240101T000000Z', 'RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240509T100000', 'DTEND;TZID=Europe/Berlin:20240509T110000', 'SUMMARY:Moved', 'END:VEVENT']));

        $items = $this->call('collectComponents', $cal, [], $this->day('2024-05-01'), $this->day('2024-06-30'), 'Europe/Berlin', true);

        $this->assertSame(['other#20240508T160000'], array_keys($items));
    }

    public function testOverrideIsAnEventOfItsOwnIfTheEventIsNotPartOfThePeriod(): void
    {
        $items = $this->call('collectComponents', $this->calendar(), [], $this->day('2024-05-01'), $this->day('2024-06-30'), 'Europe/Berlin', true);

        $this->assertSame(['series#20240508T160000'], array_keys($items));
    }

    public function testKeyOfOverridesFitsIntoTheColumn(): void
    {
        $cal = new Vcalendar();
        $cal->parse($this->ics(['BEGIN:VEVENT', 'UID:'.str_repeat('a', 300), 'DTSTAMP:20240101T000000Z', 'RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240509T100000', 'END:VEVENT']));

        $keys = array_keys($this->call('collectComponents', $cal, [], $this->day('2024-05-01'), $this->day('2024-06-30'), 'Europe/Berlin', false));

        $this->assertCount(1, $keys);
        $this->assertLessThanOrEqual(255, \strlen((string) $keys[0]));
    }

    public function testTimeshiftAppliesToTheTimesOfAMovedRecurrence(): void
    {
        $event = $this->weeklyEvent();
        $override = $this->override('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240508T100000', 'DTEND;TZID=Europe/Berlin:20240508T113000');

        $this->call('handleRecurringExceptions', $event, self::FIELDS, $this->masterOf([]), 'Europe/Berlin', 1, [$override]);

        $this->assertSame(
            [['exception' => $this->ts('2024-05-08 19:00'), 'action' => 'move', 'new_exception' => '+0 days', 'new_start' => '11:00', 'new_end' => '12:30']],
            $event->repeatExceptions,
        );
    }

    public function testCancelledOverrideIsNotImportedAsEventOfItsOwn(): void
    {
        $cal = new Vcalendar();
        $cal->parse($this->ics(['BEGIN:VEVENT', 'UID:other', 'DTSTAMP:20240101T000000Z', 'RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240508T180000', 'STATUS:CANCELLED', 'END:VEVENT']));

        $this->assertSame([], $this->call('collectComponents', $cal, [], $this->day('2024-05-01'), $this->day('2024-06-30'), 'Europe/Berlin', false));
    }

    public function testImportedEventsArePublishedUnlessTheCalendarSaysOtherwise(): void
    {
        $calendar = new CalendarModel();
        $this->assertTrue($this->call('publishImportedEvents', null));
        $this->assertTrue($this->call('publishImportedEvents', $calendar), 'setting does not exist yet');

        $calendar->ical_publish_imported = '1';
        $this->assertTrue($this->call('publishImportedEvents', $calendar));

        $calendar->ical_publish_imported = '';
        $this->assertFalse($this->call('publishImportedEvents', $calendar));
    }

    private function calendar(): Vcalendar
    {
        $cal = new Vcalendar();
        $cal->parse($this->ics([
            'BEGIN:VEVENT', 'UID:series', 'DTSTAMP:20240101T000000Z', 'DTSTART;TZID=Europe/Berlin:20240501T180000', 'DTEND;TZID=Europe/Berlin:20240501T190000', 'RRULE:FREQ=WEEKLY;COUNT=4', 'SUMMARY:Series', 'END:VEVENT',
            'BEGIN:VEVENT', 'UID:series', 'DTSTAMP:20240101T000000Z', 'RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', 'DTSTART;TZID=Europe/Berlin:20240509T180000', 'DTEND;TZID=Europe/Berlin:20240509T190000', 'SUMMARY:Moved', 'END:VEVENT',
        ]));

        return $cal;
    }

    /**
     * @param list<string> $lines
     */
    private function masterOf(array $lines): Vevent
    {
        $cal = $this->calendars[] = new Vcalendar();
        $cal->parse($this->ics(['BEGIN:VEVENT', 'UID:series', 'DTSTAMP:20240101T000000Z', 'DTSTART;TZID=Europe/Berlin:20240501T180000', 'DTEND;TZID=Europe/Berlin:20240501T190000', 'RRULE:FREQ=WEEKLY', ...$lines, 'END:VEVENT']));

        return $this->first($cal);
    }

    private function override(string ...$lines): Vevent
    {
        $cal = $this->calendars[] = new Vcalendar();
        $cal->parse($this->ics(['BEGIN:VEVENT', 'UID:series', 'DTSTAMP:20240101T000000Z', ...$lines, 'END:VEVENT']));

        return $this->first($cal);
    }

    private function first(Vcalendar $cal): Vevent
    {
        $vevent = $cal->getComponents(IcalInterface::VEVENT)[0];
        $this->assertInstanceOf(Vevent::class, $vevent);

        return $vevent;
    }

    /**
     * @param list<string> $lines
     */
    private function ics(array $lines): string
    {
        return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:x', ...$lines, 'END:VCALENDAR'])."\r\n";
    }

    private function weeklyEvent(): CalendarEventsModel
    {
        return $this->event([
            'addTime' => true,
            'startDate' => $this->ts('2024-05-01 18:00'),
            'startTime' => $this->ts('2024-05-01 18:00'),
            'endDate' => $this->ts('2024-05-01 19:00'),
            'endTime' => $this->ts('2024-05-01 19:00'),
        ]);
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

    private function ts(string $date): int
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('Europe/Berlin')))->getTimestamp();
    }

    private function day(string $date): Date
    {
        return new Date($this->ts($date));
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(IcsImport::class, $method))->invoke($this->import, ...$arguments);
    }
}
