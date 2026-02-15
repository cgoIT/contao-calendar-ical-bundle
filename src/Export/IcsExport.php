<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Export;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Date;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
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

    public function __construct(
        private readonly InsertTagParser $insertTagParser,
    ) {
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

        $ical = new Vcalendar();
        $ical->setMethod(IcalInterface::PUBLISH);
        $ical->setXprop(IcalInterface::X_WR_CALNAME, $title ?? reset($arrCalendars)->title);
        $ical->setXprop(IcalInterface::X_WR_CALDESC, $description ?? reset($arrCalendars)->ical_description);
        $ical->setXprop(IcalInterface::X_WR_TIMEZONE, Config::get('timeZone'));

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $objCalendar) {
                try {
                    $arrEvents = System::importStatic('\Cgoit\CalendarExtendedBundle\Models\CalendarEventsModelExt')->findCurrentByPid($objCalendar->id, $intStart, $intEnd);
                } catch (\Exception) {
                    $arrEvents = CalendarEventsModel::findCurrentByPid($objCalendar->id, $intStart, $intEnd);
                }

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
                        $vevent = $this->getVevent($objEvent);

                        if (null !== $vevent) {
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
                $vevent = $this->getVevent($objEvent);
                if (!empty($vevent)) {
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

        $vevent = $this->getVevent($objEvent);
        if (null !== $vevent) {
            $ical->setComponent($vevent);
        }

        return $ical;
    }

    private function getVevent(CalendarEventsModel $objEvent, string|null $prefix = null): Vevent|null
    {
        global $objPage;

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
                $tsEnd = ($startDate < (int) $objEvent->endTime)
                    ? (int) $objEvent->endTime + 1
                    : $startDate + 24 * 60 * 60;
                $dtEnd = new \DateTimeImmutable("@{$tsEnd}");
                $dtEnd = $dtEnd->setTimezone($timezone);
            }
        } else {
            $tsStart = (int) $objEvent->startTime;
            $dtStart = new \DateTimeImmutable("@{$tsStart}");
            $dtStart = $dtStart->setTimezone($timezone);
            $tsEnd = (!empty($objEvent->endTime) and $tsStart < (int) $objEvent->endTime)
                ? (int) $objEvent->endTime
                : $tsStart + 60 * 60;
            $dtEnd = new \DateTimeImmutable("@{$tsEnd}");
            $dtEnd = $dtEnd->setTimezone($timezone);
        }
        $vevent->setDtstart($dtStart, [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
        $vevent->setDtend($dtEnd, [IcalInterface::VALUE => IcalInterface::DATE_TIME]);

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
                    $attendee = trim($attendee);
                    if (str_contains($attendee, '@')) {
                        $vevent->setAttendee($attendee);
                    } else {
                        $vevent->setAttendee($attendee, ['CN' => $attendee]);
                    }
                }
            }
        }

        if (!empty($objEvent->location_contact)) {
            $contact = trim((string) $objEvent->location_contact);
            $vevent->setContact($contact);
        }

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
                        !$skipInfo['exception'] or !is_numeric($skipInfo['exception']) or
                        !$skipInfo['exceptionTo'] or !is_numeric($skipInfo['exceptionTo'])
                    ) {
                        continue;
                    }

                    $tsStart = (int)$skipInfo['exception'];
                    $tsEnd = (int)$skipInfo['exceptionTo'] + 24 * 60 * 60;

                    $affectedDates = array_filter(
                        $arrDates, fn (int $tsDate) => $tsDate >= $tsStart and $tsDate <= $tsEnd
                    );
                    if (!empty($affectedDates)) {
                        switch ($skipInfo['action']) {
                            // Termin nicht anzeigen
                            case 'hide':
                                $exDates = array_map(
                                    function (int $tsDate) use($objEvent, $timezone) {
                                        $exDate = new \DateTimeImmutable("@{$tsDate}");
                                        $exDate = $exDate->setTimezone($timezone);
                                        if (!$objEvent->addTime) {
                                            $exDate = $exDate->setTime(0, 0);
                                        }
                                        return $exDate;
                                    },
                                    $affectedDates
                                );
                                $hideDates = array_merge($hideDates, $exDates);
                                break;
                            //
                            /**
                             * Termin verschieben
                             * @todo Hier muss ein weiteres VEvent mit dem neuen Termin erzeugt werden
                             */
                            case 'move':
                                break;
                        }
                    }
                }
            }

            // Ausnahmen nach Datum
            if (!empty($objEvent->repeatExceptions)) {
                $arrSkipDates = StringUtil::deserialize($objEvent->repeatExceptions, true);

                foreach ($arrSkipDates as $skipInfo) {
                    if (!$skipInfo['exception'] or !is_numeric($skipInfo['exception'])) {
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
                        //
                        /**
                         * Termin verschieben
                         * @todo Hier muss ein weiteres VEvent mit dem neuen Termin erzeugt werden
                         */
                        case 'move':
                            $dateChangeValue = (string) $skipInfo['new_exception'];

                            // only change the start and end time if addTime is set to true for the event
                            if (!empty($objEvent->addTime) && !empty($skipInfo['new_start']) && !empty($skipInfo['new_end'])) {
                                $newStartTime = strtotime($dateChangeValue,
                                    strtotime(Date::parse($objPage->dateFormat, $startTime).' '.$skipInfo['new_start']));
                            } else {
                                $newStartTime = strtotime($dateChangeValue, $startTime);
                            }
                            $exdate =
                                \DateTime::createFromFormat(DateTimeFactory::$YmdHis,
                                    date('Y', $newStartTime).
                                    date('m', $newStartTime).
                                    date('d', $newStartTime).
                                    date('H', $newStartTime).
                                    date('i', $newStartTime).
                                    date('s', $newStartTime),
                                );
                            $vevent->setExdate($exdate);
                            break;
                    }
                }
            }

            if (!empty($hideDates)) {
                $vevent->setExdate($hideDates);
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
