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

use Cgoit\ContaoCalendarIcalBundle\Event\ModifyExportEventsEvent;
use Cgoit\ContaoCalendarIcalBundle\Event\ModifyVeventEvent;
use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Registry;
use Contao\PageModel;
use Kigkonsult\Icalcreator\Vevent;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class IcsExportTest extends ContaoTestCase
{
    private IcsExport $export;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();

        $parser = $this->createStub(InsertTagParser::class);
        $parser
            ->method('replaceInline')
            ->willReturnArgument(0)
        ;

        $this->dispatcher = new EventDispatcher();
        $this->export = new IcsExport($parser, $this->dispatcher);
        $this->createCalendar(1, ['title' => 'Club calendar']);
    }

    public function testTimeRangeUsesConfiguredBoundaries(): void
    {
        $source = new PageModel();
        $source->ical_start = (string) $this->ts('2024-05-01 00:00');
        $source->ical_end = (string) $this->ts('2024-06-01 00:00');

        $this->assertSame([$this->ts('2024-05-01 00:00'), $this->ts('2024-06-01 00:00')], $this->export->getTimeRange($source));
    }

    public function testTimeRangeFallsBackToDefaultDifference(): void
    {
        $before = time();
        [$start, $end] = $this->export->getTimeRange(new PageModel());

        $this->assertGreaterThanOrEqual($before, $start);
        $this->assertEqualsWithDelta($start + 365 * 24 * 3600, $end, 5);
    }

    public function testAllDayEventIsExportedAsDate(): void
    {
        $event = $this->createEvent(['startDate' => $this->ts('2024-05-01 00:00'), 'endDate' => null]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20240501', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20240502', $ics);
    }

    public function testMultiDayAllDayEventEndsOnDayAfterLastDay(): void
    {
        // Contao stores the end of an all day event as 23:59:59 of the last day
        $event = $this->createEvent([
            'startDate' => $this->ts('2024-05-01 00:00'),
            'endDate' => $this->ts('2024-05-03 00:00'),
            'startTime' => $this->ts('2024-05-01 00:00'),
            'endTime' => $this->ts('2024-05-03 23:59:59'),
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20240501', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20240504', $ics);
    }

    public function testTimedEventIsExportedAsDateTimeInConfiguredTimezone(): void
    {
        $event = $this->createEvent([
            'addTime' => '1',
            'startDate' => $this->ts('2024-05-01 00:00'),
            'startTime' => $this->ts('2024-05-01 18:00'),
            'endTime' => $this->ts('2024-05-01 20:30'),
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('X-WR-TIMEZONE:Europe/Berlin', $ics);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20240501T180000', $ics);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240501T203000', $ics);
    }

    public function testTimedEventWithoutEndLastsOneHour(): void
    {
        $event = $this->createEvent([
            'addTime' => '1',
            'startTime' => $this->ts('2024-05-01 18:00'),
            'endTime' => null,
        ]);

        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240501T190000', $this->exportEvent($event));
    }

    public function testTimedEventAcrossDaylightSavingChange(): void
    {
        // Clocks change on 2024-03-31 at 02:00 in Europe/Berlin
        $event = $this->createEvent([
            'addTime' => '1',
            'startTime' => $this->ts('2024-03-31 12:00'),
            'endTime' => $this->ts('2024-03-31 14:00'),
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20240331T120000', $ics);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240331T140000', $ics);
    }

    public function testExportRespectsConfiguredTimezone(): void
    {
        $GLOBALS['TL_CONFIG']['timeZone'] = 'America/New_York';
        $event = $this->createEvent([
            'addTime' => '1',
            'startTime' => $this->ts('2024-05-01 18:00'), // 12:00 in New York
            'endTime' => $this->ts('2024-05-01 20:00'),
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('X-WR-TIMEZONE:America/New_York', $ics);
        $this->assertStringContainsString('DTSTART;TZID=America/New_York:20240501T120000', $ics);
    }

    public function testEventWithoutStartIsSkipped(): void
    {
        $event = $this->createEvent(['startDate' => null, 'startTime' => null]);

        $this->assertStringNotContainsString('BEGIN:VEVENT', $this->exportEvent($event));
    }

    public function testSummaryDescriptionAndLocationAreDecodedAndCleaned(): void
    {
        $event = $this->createEvent([
            'title' => 'Tom &amp; Jerry&#039;s',
            'teaser' => '<p>First<br />Second &amp; third</p>',
            'location' => '  Town hall &amp; garden  ',
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString("SUMMARY:Tom & Jerry's", $ics);
        $this->assertStringContainsString('DESCRIPTION:First\nSecond & third', $ics);
        $this->assertStringContainsString('LOCATION:Town hall & garden', $ics);
    }

    public function testCalendarNameAndDescriptionAreDecoded(): void
    {
        $ics = $this->export->getVcalendar([], 0, 0, 'Tom &amp; Jerry&#039;s &#92;&#92; Club', 'Cats &amp; mice')->createCalendar();

        $this->assertStringContainsString("X-WR-CALNAME:Tom & Jerry's \\\\ Club", $ics);
        $this->assertStringContainsString('X-WR-CALDESC:Cats & mice', $ics);
    }

    public function testCalendarPrefixIsPrependedToSummary(): void
    {
        $this->createCalendar(2, ['title' => 'With prefix', 'ical_prefix' => '[Club]']);
        $event = $this->createEvent(['pid' => 2, 'title' => 'Training']);

        $this->assertStringContainsString('SUMMARY:[Club] Training', $this->exportEvent($event));
    }

    public function testDailyRecurrenceWithIntervalAndCount(): void
    {
        $event = $this->createEvent([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'days', 'value' => 2]),
            'recurrences' => 5,
        ]);

        $this->assertStringContainsString('RRULE:FREQ=DAILY;COUNT=6;INTERVAL=2', $this->exportEvent($event));
    }

    public function testWeeklyRecurrenceWithoutLimitHasNoCount(): void
    {
        $event = $this->createEvent([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'recurrences' => 0,
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('RRULE:FREQ=WEEKLY', $ics);
        $this->assertStringNotContainsString('COUNT=', $ics);
        $this->assertStringNotContainsString('INTERVAL=', $ics);
    }

    public function testMonthlyAndYearlyRecurrenceUnits(): void
    {
        $monthly = $this->createEvent(['recurring' => '1', 'repeatEach' => serialize(['unit' => 'months', 'value' => 1])]);
        $yearly = $this->createEvent(['recurring' => '1', 'repeatEach' => serialize(['unit' => 'years', 'value' => 1])]);

        $this->assertStringContainsString('RRULE:FREQ=MONTHLY', $this->exportEvent($monthly));
        $this->assertStringContainsString('RRULE:FREQ=YEARLY', $this->exportEvent($yearly));
    }

    public function testExtendedMonthlyRecurrenceOnWeekdayOfMonth(): void
    {
        $event = $this->createEvent([
            'recurringExt' => '1',
            'repeatEachExt' => serialize(['unit' => 'thursday', 'value' => 'first']),
            'recurrences' => 3,
        ]);

        $this->assertStringContainsString('RRULE:FREQ=MONTHLY;COUNT=4;INTERVAL=1;BYDAY=1TH', $this->exportEvent($event));
    }

    public function testExtendedMonthlyRecurrenceOnLastWeekday(): void
    {
        $event = $this->createEvent([
            'recurringExt' => '1',
            'repeatEachExt' => serialize(['unit' => 'friday', 'value' => 'last']),
        ]);

        $this->assertStringContainsString('BYDAY=-1FR', $this->exportEvent($event));
    }

    public function testHiddenRecurrenceExceptionsBecomeExdates(): void
    {
        $event = $this->createEvent([
            'addTime' => '1',
            'startTime' => $this->ts('2024-05-01 18:00'),
            'endTime' => $this->ts('2024-05-01 19:00'),
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'useExceptions' => '1',
            'repeatExceptions' => serialize([
                ['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'hide'],
                ['exception' => $this->ts('2024-05-15 18:00'), 'action' => 'hide'],
            ]),
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('EXDATE;TZID=Europe/Berlin:20240508T180000,20240515T180000', $ics);
    }

    public function testExceptionsAreIgnoredWithoutUseExceptions(): void
    {
        $event = $this->createEvent([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'repeatExceptions' => serialize([['exception' => $this->ts('2024-05-08 00:00'), 'action' => 'hide']]),
        ]);

        $this->assertStringNotContainsString('EXDATE', $this->exportEvent($event));
    }

    public function testExceptionsOfAllDayEventsAreExportedAsDates(): void
    {
        $event = $this->createEvent([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'useExceptions' => '1',
            'repeatExceptions' => serialize([
                ['exception' => $this->ts('2024-05-08 00:00'), 'action' => 'hide'],
                ['exception' => $this->ts('2024-05-15 00:00'), 'action' => 'hide'],
            ]),
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20240501', $ics);
        $this->assertStringContainsString('EXDATE;VALUE=DATE:20240508,20240515', $ics);
    }

    public function testParticipantsAndContact(): void
    {
        $event = $this->createEvent([
            'cep_participants' => 'jane@example.com, john@example.com',
            'location_contact' => ' Mr. Contact ',
        ]);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('ATTENDEE:mailto:jane@example.com', $ics);
        $this->assertStringContainsString('ATTENDEE:mailto:john@example.com', $ics);
        $this->assertStringContainsString('CONTACT:Mr. Contact', $ics);
    }

    public function testParticipantWithoutEmailAddressIsSkipped(): void
    {
        // iCalcreator only accepts e-mail addresses as ATTENDEE; a name must not abort the export
        $event = $this->createEvent(['cep_participants' => 'Max Mustermann, jane@example.com, broken@']);

        $ics = $this->exportEvent($event);

        $this->assertStringContainsString('ATTENDEE:mailto:jane@example.com', $ics);
        $this->assertStringNotContainsString('Mustermann', $ics);
        $this->assertSame(1, substr_count($ics, 'ATTENDEE'));
    }

    public function testCalendarWithoutCalendarsIsEmptyInsteadOfAnError(): void
    {
        foreach ([null, []] as $calendars) {
            $ics = $this->export->getVcalendar($calendars, $this->ts('2024-05-01 00:00'), $this->ts('2025-05-01 00:00'))?->createCalendar();

            $this->assertStringContainsString('BEGIN:VCALENDAR', (string) $ics);
            $this->assertStringNotContainsString('BEGIN:VEVENT', (string) $ics);
        }
    }

    public function testCalendarWithoutCalendarsUsesGivenTitle(): void
    {
        $ics = $this->export->getVcalendar([], $this->ts('2024-05-01 00:00'), $this->ts('2025-05-01 00:00'), 'My feed')?->createCalendar();

        $this->assertStringContainsString('X-WR-CALNAME:My feed', (string) $ics);
    }

    public function testMovedRecurrenceBecomesOverrideWithSameUid(): void
    {
        $event = $this->createWeeklyTimedEvent([
            ['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'move', 'new_exception' => '+1 day', 'new_start' => '', 'new_end' => ''],
        ]);

        $ics = $this->exportEvent($event);

        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringNotContainsString('EXDATE', $ics, 'The override replaces the recurrence, it must not be excluded as well');

        [$main, $moved] = $this->events($ics);

        $this->assertSame($this->property($main, 'UID'), $this->property($moved, 'UID'));
        $this->assertStringContainsString('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', $moved);
        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20240509T180000', $moved);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240509T190000', $moved);
        $this->assertStringContainsString('SUMMARY:Event', $moved);
        $this->assertStringNotContainsString('RRULE', $moved);
    }

    public function testMovedRecurrenceCanHaveNewTimes(): void
    {
        $event = $this->createWeeklyTimedEvent([
            ['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'move', 'new_exception' => '+2 days', 'new_start' => '19:00', 'new_end' => '20:30'],
        ]);

        [, $moved] = $this->events($this->exportEvent($event));

        $this->assertStringContainsString('DTSTART;TZID=Europe/Berlin:20240510T190000', $moved);
        $this->assertStringContainsString('DTEND;TZID=Europe/Berlin:20240510T203000', $moved);
    }

    public function testMovedRecurrenceOfAllDayEvent(): void
    {
        $event = $this->createEvent([
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'useExceptions' => '1',
            'repeatExceptions' => serialize([
                ['exception' => $this->ts('2024-05-08 00:00'), 'action' => 'move', 'new_exception' => '+1 day'],
            ]),
        ]);

        [, $moved] = $this->events($this->exportEvent($event));

        $this->assertStringContainsString('RECURRENCE-ID;VALUE=DATE:20240508', $moved);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20240509', $moved);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20240510', $moved);
    }

    public function testRecurrencesMovedByPeriodBecomeOverrides(): void
    {
        $event = $this->createWeeklyTimedEvent([], [
            ['exception' => $this->ts('2024-05-08 00:00'), 'exceptionTo' => $this->ts('2024-05-15 00:00'), 'action' => 'move', 'new_exception' => '+1 day', 'new_start' => '', 'new_end' => ''],
        ]);
        $event->allRecurrences = serialize([
            $this->ts('2024-05-08 18:00') => [],
            $this->ts('2024-05-15 18:00') => [],
            $this->ts('2024-05-22 18:00') => [],
        ]);

        $ics = $this->exportEvent($event);

        $this->assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('RECURRENCE-ID;TZID=Europe/Berlin:20240508T180000', $ics);
        $this->assertStringContainsString('RECURRENCE-ID;TZID=Europe/Berlin:20240515T180000', $ics);
        $this->assertStringNotContainsString('RECURRENCE-ID;TZID=Europe/Berlin:20240522T180000', $ics);
    }

    public function testMoveWithoutValidTargetIsIgnored(): void
    {
        $event = $this->createWeeklyTimedEvent([
            ['exception' => $this->ts('2024-05-08 18:00'), 'action' => 'move', 'new_exception' => ''],
        ]);

        $this->assertSame(1, substr_count($this->exportEvent($event), 'BEGIN:VEVENT'));
    }

    public function testExportEventsAcceptsModelsAndEventRows(): void
    {
        $first = $this->createEvent(['id' => 10, 'title' => 'First']);
        $this->createEvent(['id' => 11, 'title' => 'Second']);

        $ics = $this->export->exportEvents([$first, ['id' => 11]])->createCalendar();

        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('SUMMARY:First', $ics);
        $this->assertStringContainsString('SUMMARY:Second', $ics);
    }

    public function testExportEventsExportsEveryEventOnlyOnce(): void
    {
        $event = $this->createEvent(['id' => 10, 'title' => 'Weekly']);

        // Every occurrence of an event is an entry of the list, as arrays (events generator) or models
        $ics = $this->export->exportEvents([['id' => 10], $event, ['id' => 10]])->createCalendar();

        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
    }

    public function testExportEventsWithoutEventsContainsNoVevent(): void
    {
        $ics = $this->export->exportEvents([])->createCalendar();

        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('METHOD:PUBLISH', $ics);
    }

    #[IgnoreDeprecations]
    public function testModifyVeventHookCanChangeTheEvent(): void
    {
        $GLOBALS['TL_HOOKS']['icalModifyVevent'] = [[VeventHook::class, 'modify']];
        $event = $this->createEvent(['title' => 'Original']);

        $this->assertStringContainsString('SUMMARY:Changed by hook', $this->exportEvent($event));
    }

    public function testModifyVeventEventCanChangeTheEvent(): void
    {
        $this->dispatcher->addListener(
            ModifyVeventEvent::class,
            static function (ModifyVeventEvent $event): void {
                $event->vevent->setSummary('Changed by '.$event->calendarEventModel->title);
            },
        );
        $event = $this->createEvent(['title' => 'Original']);

        $this->assertStringContainsString('SUMMARY:Changed by Original', $this->exportEvent($event));
    }

    #[IgnoreDeprecations]
    public function testGetAllEventsHookReturningNothingKeepsTheEvents(): void
    {
        $GLOBALS['TL_HOOKS']['icalGetAllEvents'] = [[VeventHook::class, 'nothing']];
        $keep = $this->createEvent(['title' => 'Keep']);

        $method = new \ReflectionMethod($this->export, 'modifyEvents');
        $result = $method->invoke($this->export, [$keep], CalendarModel::findById(1), [CalendarModel::findById(1)], 0, 1);

        $this->assertSame([$keep], $result);
    }

    public function testModifyExportEventsEventCanRemoveEvents(): void
    {
        $this->dispatcher->addListener(
            ModifyExportEventsEvent::class,
            static function (ModifyExportEventsEvent $event): void {
                $event->events = array_values(array_filter($event->events, static fn (CalendarEventsModel $model): bool => 'Keep' === $model->title));
            },
        );
        $keep = $this->createEvent(['id' => 10, 'title' => 'Keep']);
        $drop = $this->createEvent(['id' => 11, 'title' => 'Drop']);

        $events = [$keep, $drop];
        $method = new \ReflectionMethod($this->export, 'modifyEvents');
        $result = $method->invoke($this->export, $events, CalendarModel::findById(1), [CalendarModel::findById(1)], 0, 1);

        $this->assertSame([$keep], $result);
    }

    /**
     * @param array<mixed> $exceptions
     * @param array<mixed> $periods
     */
    private function createWeeklyTimedEvent(array $exceptions, array $periods = []): CalendarEventsModel
    {
        return $this->createEvent([
            'addTime' => '1',
            'startTime' => $this->ts('2024-05-01 18:00'),
            'endTime' => $this->ts('2024-05-01 19:00'),
            'recurring' => '1',
            'repeatEach' => serialize(['unit' => 'weeks', 'value' => 1]),
            'useExceptions' => '1',
            'repeatExceptions' => [] === $exceptions ? null : serialize($exceptions),
            'repeatExceptionsPer' => [] === $periods ? null : serialize($periods),
        ]);
    }

    /**
     * @return array<string>
     */
    private function events(string $ics): array
    {
        preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $ics, $matches);

        return $matches[0];
    }

    private function property(string $vevent, string $name): string
    {
        preg_match('/^'.$name.':(.*)$/m', $vevent, $matches);

        return trim($matches[1] ?? '');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCalendar(int $id, array $data = []): CalendarModel
    {
        $calendar = new CalendarModel();
        $calendar->id = $id;
        $calendar->title = 'Calendar '.$id;

        foreach ($data as $key => $value) {
            $calendar->{$key} = $value;
        }

        Registry::getInstance()->register($calendar);

        return $calendar;
    }

    /**
     * Creates an all day event on 2024-05-01 by default.
     *
     * @param array<string, mixed> $data
     */
    private function createEvent(array $data = []): CalendarEventsModel
    {
        static $nextId = 100;

        $data += [
            'id' => $nextId++,
            'pid' => 1,
            'title' => 'Event',
            'addTime' => '',
            'startDate' => $this->ts('2024-05-01 00:00'),
            'endDate' => null,
        ];

        $event = new CalendarEventsModel();

        foreach ($data as $key => $value) {
            $event->{$key} = $value;
        }

        Registry::getInstance()->register($event);

        return $event;
    }

    private function exportEvent(CalendarEventsModel $event): string
    {
        return $this->export->exportEvent($event)->createCalendar();
    }

    private function ts(string $dateTime): int
    {
        return (new \DateTimeImmutable($dateTime, new \DateTimeZone('Europe/Berlin')))->getTimestamp();
    }
}

class VeventHook
{
    public function nothing(): void
    {
    }

    public function modify(Vevent $vevent): Vevent
    {
        return $vevent->setSummary('Changed by hook');
    }
}
