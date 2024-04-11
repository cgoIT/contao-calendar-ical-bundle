<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Export;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Collection;
use Contao\StringUtil;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class IcsExport extends Backend
{
    public function __construct(
        private readonly InsertTagParser $insertTagParser,
    ) {
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
                        $vevent = new Vevent();

                        $startDate = $objEvent->startDate ?? $objEvent->startTime;
                        $endDate = $objEvent->endDate ?? $objEvent->endTime;

                        if (!empty($startDate)) {
                            if (!empty($objEvent->addTime)) {
                                $vevent->setDtstart(date(DateTimeFactory::$YmdTHis, $objEvent->startTime), [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                                if (!empty($objEvent->endTime)) {
                                    if ((int) $objEvent->startTime < (int) $objEvent->endTime) {
                                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvent->endTime),
                                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                                    } else {
                                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvent->startTime + 60 * 60),
                                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                                    }
                                } else {
                                    $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $objEvent->startTime + 60 * 60),
                                        [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                                }
                            } else {
                                $vevent->setDtstart(date(DateTimeFactory::$Ymd, $startDate), [IcalInterface::VALUE => IcalInterface::DATE]);
                                if (!empty($endDate)) {
                                    if ((int) $startDate < (int) $endDate) {
                                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $endDate),
                                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                                    } else {
                                        $vevent->setDtend(date(DateTimeFactory::$YmdTHis, $startDate + 60 * 60),
                                            [IcalInterface::VALUE => IcalInterface::DATE_TIME]);
                                    }
                                } else {
                                    $vevent->setDtend(date(DateTimeFactory::$Ymd, $startDate + 24 * 60 * 60),
                                        [IcalInterface::VALUE => IcalInterface::DATE]);
                                }
                            }

                            $summary = $objEvent->title;
                            if (!empty($prefix)) {
                                $summary = $prefix.' '.$summary;
                            } elseif (!empty($objCalendar->ical_prefix)) {
                                $summary = $objCalendar->ical_prefix.' '.$summary;
                            }
                            $vevent->setSummary(html_entity_decode((string) $summary, ENT_QUOTES, 'UTF-8'));

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
                                $arg = $arrRepeat['value'];

                                $freq = 'YEARLY';

                                switch ($arrRepeat['unit']) {
                                    case 'days':
                                        $freq = 'DAILY';
                                        break;
                                    case 'weeks':
                                        $freq = 'WEEKLY';
                                        break;
                                    case 'months':
                                        $freq = 'MONTHLY';
                                        break;
                                    case 'years':
                                        $freq = 'YEARLY';
                                        break;
                                }

                                $rrule = ['FREQ' => $freq];

                                if ($objEvent->recurrences > 0) {
                                    $rrule['count'] = $objEvent->recurrences;
                                }

                                if ($arg > 1) {
                                    $rrule['INTERVAL'] = $arg;
                                }

                                $vevent->setRrule($rrule);
                            }

                            /*
                            * begin module event_recurrences handling
                            */
                            if (!empty($objEvent->repeatExceptions)) {
                                $arrSkipDates = StringUtil::deserialize($objEvent->repeatExceptions, true);

                                foreach ($arrSkipDates as $skipDate) {
                                    $exTStamp = strtotime((string) $skipDate);
                                    $exdate =
                                        \DateTime::createFromFormat(DateTimeFactory::$YmdHis,
                                            date('Y', $exTStamp).
                                            date('m', $exTStamp).
                                            date('d', $exTStamp).
                                            date('H', $objEvent->startTime).
                                            date('i', $objEvent->startTime).
                                            date('s', $objEvent->startTime),
                                        );
                                    $vevent->setExdate($exdate);
                                }
                            }
                            /*
                            * end module event_recurrences handling
                            */

                            $ical->setComponent($vevent);
                        }
                    }
                }
            }
        }

        return $ical;
    }
}
