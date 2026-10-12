<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Export;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Collection;
use Contao\StringUtil;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class IcsExport extends Backend
{
    /**
     * @var array<mixed>
     */
    private readonly array $dayMap;

    /**
     * @var array<mixed>
     */
    private readonly array $posMap;

    public function __construct(private readonly InsertTagParser $insertTagParser)
    {
        $this->dayMap = [
            'monday' => Vcalendar::MO,
            'tuesday' => Vcalendar::TU,
            'wednesday' => Vcalendar::WE,
            'thursday' => Vcalendar::TH,
            'friday' => Vcalendar::FR,
            'saturday' => Vcalendar::SA,
            'sunday' => Vcalendar::SU,
        ];

        $this->posMap = [
            'first' => 1,
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
            'fifth' => 5,
            'last' => -1,
        ];
    }

    /**
     * @param Collection<CalendarModel>|array<CalendarModel>|null $arrCalendars
     */
    public function getVcalendar(Collection|array|null $arrCalendars, int $intStart, int $intEnd, string|null $title = null, string|null $description = null, string|null $prefix = null): Vcalendar|null
    {
        if (null !== $arrCalendars && $arrCalendars instanceof Collection) {
            $arrCalendars = $arrCalendars->getModels();
        }

        // The list may be empty, e.g. if the selected calendars were deleted or are not accessible
        $firstCalendar = !empty($arrCalendars) ? reset($arrCalendars) : null;

        $ical = new Vcalendar();
        $ical->setMethod(IcalInterface::PUBLISH);
        $ical->setXprop(IcalInterface::X_WR_CALNAME, $title ?? $firstCalendar?->title ?? '');
        $ical->setXprop(IcalInterface::X_WR_CALDESC, $description ?? $firstCalendar?->ical_description ?? '');
        $ical->setXprop(IcalInterface::X_WR_TIMEZONE, Config::get('timeZone'));

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $objCalendar) {
                $arrEvents = CalendarEventsModel::findCurrentByPid($objCalendar->id, $intStart, $intEnd);

                if (null !== $arrEvents) {
                    // HOOK: modify the result set
                    if (isset($GLOBALS['TL_HOOKS']['icalGetAllEvents']) && \is_array($GLOBALS['TL_HOOKS']['icalGetAllEvents'])) {
                        foreach ($GLOBALS['TL_HOOKS']['icalGetAllEvents'] as $callback) {
                            $this->import($callback[0]);
                            $arrEvents = $this->{$callback[0]}->{$callback[1]}($arrEvents->getModels(), $arrCalendars, $intStart, $intEnd, $this);
                        }
                    }

                    /** @var CalendarEventsModel $objEvent */
                    foreach ($arrEvents as $objEvent) {
                        foreach ($this->getVevents($objEvent, $prefix) as $vevent) {
                            $ical->setComponent($vevent);
                        }
                    }
                }
            }
        }

        return $ical;
    }

    /**
     * @param array<mixed> $arrEvents
     */
    public function exportEvents(array $arrEvents): Vcalendar
    {
        $ical = new Vcalendar();
        $ical->setMethod(IcalInterface::PUBLISH);
        $ical->setXprop(IcalInterface::X_WR_TIMEZONE, Config::get('timeZone'));

        foreach ($arrEvents as $arrEvent) {
            $objEvent = $arrEvent;
            if (\is_array($arrEvent)) {
                $objEvent = CalendarEventsModel::findById($arrEvent['id']);
            }

            if (!empty($objEvent)) {
                foreach ($this->getVevents($objEvent) as $vevent) {
                    $ical->setComponent($vevent);
                }
            }
        }

        return $ical;
    }

    public function exportEvent(CalendarEventsModel $objEvent): Vcalendar
    {
        $ical = new Vcalendar();
        $ical->setMethod(IcalInterface::PUBLISH);
        $ical->setXprop(IcalInterface::X_WR_TIMEZONE, Config::get('timeZone'));

        foreach ($this->getVevents($objEvent) as $vevent) {
            $ical->setComponent($vevent);
        }

        return $ical;
    }

    /**
     * Sets title, description, location, participants and contact.
     */
    private function applyDetails(Vevent $vevent, CalendarEventsModel $objEvent, CalendarModel|null $objCalendar, string|null $prefix): void
    {
        $summary = $objEvent->title;
        if (!empty($prefix)) {
            $summary = $prefix.' '.$summary;
        } elseif (!empty($objCalendar->ical_prefix)) {
            $summary = $objCalendar->ical_prefix.' '.$summary;
        }
        $vevent->setSummary(html_entity_decode($summary, ENT_QUOTES, 'UTF-8'));

        if (!empty($objEvent->teaser)) {
            $vevent->setDescription(html_entity_decode(strip_tags((string) preg_replace('/<br \\/>/', "\n",
                $this->insertTagParser->replaceInline($objEvent->teaser))),
                ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($objEvent->location)) {
            $vevent->setLocation(trim(html_entity_decode((string) $objEvent->location, ENT_QUOTES, 'UTF-8')));
        }

        if (!empty($objEvent->cep_participants)) {
            $attendees = preg_split('/,/', (string) $objEvent->cep_participants);
            if (is_countable($attendees) ? \count($attendees) : 0) {
                foreach ($attendees as $attendee) {
                    // An ATTENDEE has to be an e-mail address; names without one cannot be exported
                    $attendee = trim($attendee);
                    if (false === filter_var($attendee, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    $vevent->setAttendee($attendee);
                }
            }
        }

        if (!empty($objEvent->location_contact)) {
            $contact = trim((string) $objEvent->location_contact);
            $vevent->setContact($contact);
        }
    }

    /**
     * Creates the event of a moved recurrence: same UID, RECURRENCE-ID of the original recurrence and the
     * new date. The rules are the same as in the calendar-extended-bundle (relative move, optional new times).
     *
     * @param array<string, mixed> $exception
     */
    private function createMovedVevent(Vevent $vevent, CalendarEventsModel $objEvent, CalendarModel|null $objCalendar, string|null $prefix, int $occurrenceStart, array $exception, \DateTimeZone $timezone, \DateTimeImmutable $dtStart, \DateTimeImmutable $dtEnd, string $valueType): Vevent|null
    {
        $move = trim((string) ($exception['new_exception'] ?? ''));

        if ('' === $move) {
            return null;
        }

        // All calculations use the time zone of the export and do not depend on the default time zone of PHP
        $original = (new \DateTimeImmutable("@{$occurrenceStart}"))->setTimezone($timezone);

        try {
            if (!$objEvent->addTime) {
                $originalStart = $original->setTime(0, 0);
                $newStartDate = $original->modify($move)->setTime(0, 0);
                $newEndDate = $newStartDate->modify('+'.max(1, (int) $dtStart->diff($dtEnd)->days).' days');
            } else {
                $originalStart = $original;

                if (!empty($exception['new_start']) && !empty($exception['new_end'])) {
                    $newStartDate = (new \DateTimeImmutable($original->format('Y-m-d').' '.$exception['new_start'], $timezone))->modify($move);
                    $endDayOffset = (int) (new \DateTimeImmutable($dtStart->format('Y-m-d')))->diff(new \DateTimeImmutable($dtEnd->format('Y-m-d')))->days;
                    $newEndDate = new \DateTimeImmutable($newStartDate->modify('+'.$endDayOffset.' days')->format('Y-m-d').' '.$exception['new_end'], $timezone);
                } else {
                    $newStartDate = $original->modify($move);
                    $newEndDate = $newStartDate->modify('+'.($dtEnd->getTimestamp() - $dtStart->getTimestamp()).' seconds');
                }
            }
        } catch (\Exception) {
            // not a valid relative date or time
            return null;
        }

        $params = [IcalInterface::VALUE => $valueType];

        $moved = new Vevent();
        $moved->setUid($vevent->getUid());
        $moved->setRecurrenceid($originalStart, $params);
        $moved->setDtstart($newStartDate, $params);
        $moved->setDtend($newEndDate, $params);
        $this->applyDetails($moved, $objEvent, $objCalendar, $prefix);

        return $moved;
    }

    /**
     * Returns the event and, for recurrences that were moved by an exception, one additional event per
     * moved recurrence (same UID and RECURRENCE-ID).
     *
     * @return array<Vevent>
     */
    private function getVevents(CalendarEventsModel $objEvent, string|null $prefix = null): array
    {
        $overrides = [];
        $vevent = $this->getVevent($objEvent, $prefix, $overrides);

        return null === $vevent ? [] : [$vevent, ...$overrides];
    }

    /**
     * @param array<Vevent> $overrides collects the events of moved recurrences
     */
    private function getVevent(CalendarEventsModel $objEvent, string|null $prefix, array &$overrides): Vevent|null
    {
        $objCalendar = CalendarModel::findById($objEvent->pid);

        $startDate = $objEvent->startDate ?? $objEvent->startTime;
        $endDate = $objEvent->endDate ?? $objEvent->endTime;
        if (empty($startDate)) {
            return null;
        }

        $vevent = new Vevent();
        $timezone = new \DateTimeZone(Config::get('timeZone'));

        if (!$objEvent->addTime) {
            $tsStart = $startDate;
            $dtStart = new \DateTimeImmutable("@{$tsStart}");
            $dtStart = $dtStart->setTimezone($timezone)->setTime(0, 0);
            if (empty($endDate)) {
                $tsEnd = $startDate + 24 * 60 * 60;
                $dtEnd = new \DateTimeImmutable("@{$tsEnd}");
                $dtEnd = $dtEnd->setTimezone($timezone)->setTime(0, 0);
            } else {
                // add one second because in ICS the end date is exclusive, in Contao its inclusive
                // and the time part is always 235959.
                $tsEnd = $startDate < (int) $objEvent->endTime
                    ? (int) $objEvent->endTime + 1
                    : $startDate + 24 * 60 * 60;
                $dtEnd = new \DateTimeImmutable("@{$tsEnd}");
                $dtEnd = $dtEnd->setTimezone($timezone);
            }

            $valueType = IcalInterface::DATE;
        } else {
            $tsStart = (int) $objEvent->startTime;
            $dtStart = new \DateTimeImmutable("@{$tsStart}");
            $dtStart = $dtStart->setTimezone($timezone);
            $tsEnd = !empty($objEvent->endTime) && $tsStart < (int) $objEvent->endTime
                ? (int) $objEvent->endTime
                : $tsStart + 60 * 60;
            $dtEnd = new \DateTimeImmutable("@{$tsEnd}");
            $dtEnd = $dtEnd->setTimezone($timezone);

            $valueType = IcalInterface::DATE_TIME;
        }
        $vevent->setDtstart($dtStart, [IcalInterface::VALUE => $valueType]);
        $vevent->setDtend($dtEnd, [IcalInterface::VALUE => $valueType]);

        $this->applyDetails($vevent, $objEvent, $objCalendar, $prefix);

        if ($objEvent->recurring) {
            $arrRepeat = StringUtil::deserialize($objEvent->repeatEach, true);

            if (!empty($arrRepeat)) {
                $arg = $arrRepeat['value'];

                $freq = Vcalendar::YEARLY;

                switch ($arrRepeat['unit']) {
                    case 'days':
                        $freq = Vcalendar::DAILY;
                        break;
                    case 'weeks':
                        $freq = Vcalendar::WEEKLY;
                        break;
                    case 'months':
                        $freq = Vcalendar::MONTHLY;
                        break;
                    case 'years':
                        $freq = Vcalendar::YEARLY;
                        break;
                }

                $rrule = [Vcalendar::FREQ => $freq];

                if ($objEvent->recurrences > 0) {
                    $rrule[Vcalendar::COUNT] = $objEvent->recurrences;
                }

                if ($arg > 1) {
                    $rrule[Vcalendar::INTERVAL] = $arg;
                }

                $vevent->setRrule($rrule);
            }
        } elseif (!empty($objEvent->recurringExt)) {
            $arrRepeat = StringUtil::deserialize($objEvent->repeatEachExt, true);
            $unit = $arrRepeat['unit']; // thursday
            $arg = $arrRepeat['value']; // first

            $byDay = ['0' => $this->posMap[$arg], Vcalendar::DAY => $this->dayMap[$unit]];

            $rrule = [
                Vcalendar::FREQ => Vcalendar::MONTHLY,
                Vcalendar::INTERVAL => 1,
                Vcalendar::BYDAY => $byDay,
            ];

            if ($objEvent->recurrences > 0) {
                $rrule[Vcalendar::COUNT] = $objEvent->recurrences;
            }

            $vevent->setRrule($rrule);
        }

        /*
        * begin module event_recurrences handling
        */
        if (!empty($objEvent->useExceptions)) {
            $hideDates = [];

            // Ausnahmen nach Zeitraum
            if (!empty($objEvent->repeatExceptionsPer)) {
                $arrSkipPeriods = StringUtil::deserialize($objEvent->repeatExceptionsPer, true);

                $arrDates = StringUtil::deserialize($objEvent->allRecurrences);
                $arrDates = \is_array($arrDates) ? array_keys($arrDates) : [];

                foreach ($arrSkipPeriods as $skipInfo) {
                    if (
                        !$skipInfo['exception'] || !is_numeric($skipInfo['exception'])
                        || !$skipInfo['exceptionTo'] || !is_numeric($skipInfo['exceptionTo'])
                    ) {
                        continue;
                    }

                    $tsStart = (int) $skipInfo['exception'];
                    $tsEnd = (int) $skipInfo['exceptionTo'] + 24 * 60 * 60;

                    $affectedDates = array_filter(
                        $arrDates, static fn (int $tsDate) => $tsDate >= $tsStart && $tsDate <= $tsEnd,
                    );
                    if (!empty($affectedDates)) {
                        switch ($skipInfo['action']) {
                            // Termin nicht anzeigen
                            case 'hide':
                                $exDates = array_map(
                                    static function (int $tsDate) use ($objEvent, $timezone) {
                                        $exDate = new \DateTimeImmutable("@{$tsDate}");
                                        $exDate = $exDate->setTimezone($timezone);
                                        if (!$objEvent->addTime) {
                                            $exDate = $exDate->setTime(0, 0);
                                        }

                                        return $exDate;
                                    },
                                    $affectedDates,
                                );
                                $hideDates = array_merge($hideDates, $exDates);
                                break;

                            // Termin verschieben
                            case 'move':
                                foreach ($affectedDates as $tsDate) {
                                    $moved = $this->createMovedVevent($vevent, $objEvent, $objCalendar, $prefix, (int) $tsDate, $skipInfo, $timezone, $dtStart, $dtEnd, $valueType);

                                    if (null !== $moved) {
                                        $overrides[] = $moved;
                                    }
                                }
                                break;
                        }
                    }
                }
            }

            // Ausnahmen nach Datum
            if (!empty($objEvent->repeatExceptions)) {
                $arrSkipDates = StringUtil::deserialize($objEvent->repeatExceptions, true);

                foreach ($arrSkipDates as $skipInfo) {
                    if (!$skipInfo['exception'] || !is_numeric($skipInfo['exception'])) {
                        continue;
                    }

                    $startTime = (int) $skipInfo['exception'];

                    switch ($skipInfo['action']) {
                        // Termin nicht anzeigen
                        case 'hide':
                            $exDate = new \DateTimeImmutable("@{$startTime}");
                            $exDate = $exDate->setTimezone($timezone);
                            if (!$objEvent->addTime) {
                                $exDate = $exDate->setTime(0, 0);
                            }
                            $hideDates[] = $exDate;
                            break;

                        // Termin verschieben
                        case 'move':
                            $moved = $this->createMovedVevent($vevent, $objEvent, $objCalendar, $prefix, $startTime, $skipInfo, $timezone, $dtStart, $dtEnd, $valueType);

                            if (null !== $moved) {
                                $overrides[] = $moved;
                            }
                            break;
                    }
                }
            }

            if (!empty($hideDates)) {
                // The value type has to match the one of DTSTART (RFC 5545)
                $vevent->setExdate($hideDates, IcalInterface::DATE === $valueType ? [IcalInterface::VALUE => IcalInterface::DATE] : []);
            }
        }

        // HOOK: modify the $vevent
        if (isset($GLOBALS['TL_HOOKS']['icalModifyVevent']) && \is_array($GLOBALS['TL_HOOKS']['icalModifyVevent'])) {
            foreach ($GLOBALS['TL_HOOKS']['icalModifyVevent'] as $callback) {
                $this->import($callback[0]);
                $vevent = $this->{$callback[0]}->{$callback[1]}($vevent, $objEvent, $objCalendar, $this);
            }
        }

        return $vevent;
    }
}
