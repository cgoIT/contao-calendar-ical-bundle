<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Cgoit\ContaoCalendarIcalBundle\Event\AfterImportItemEvent;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\File;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Pc;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Util\DateTimeZoneFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IcsImport extends AbstractImport
{
    /**
     * @var array<mixed>
     */
    private readonly array $arrMonths;

    private readonly int $maxRepeatCount;

    public function __construct(
        private readonly Connection $db,
        Slug $slug,
        private readonly ContaoFramework $contaoFramework,
        private readonly int $defaultEndTimeDifference,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($slug);

        $this->contaoFramework->initialize();

        System::loadLanguageFile('default', 'en', true);
        $this->arrMonths = $GLOBALS['TL_LANG']['MONTHS'];
        System::loadLanguageFile('default');

        try {
            $this->maxRepeatCount = System::getContainer()->getParameter('cgoit_calendar_extended.max_repeat_count');
        } catch (InvalidArgumentException) {
            $this->maxRepeatCount = 365;
        }
    }

    public function importIcsForCalendar(CalendarModel $objCalendar, bool $force_import = false): void
    {
        if (!empty($objCalendar->ical_source) && !empty($objCalendar->ical_url)) {
            $last_change = (int) $objCalendar->ical_last_sync;
            if (empty($last_change)) {
                $last_change = time();
                $force_import = true;
            }

            if (((time() - $last_change > $objCalendar->ical_cache) && (1 !== $objCalendar->ical_importing || (time() - $objCalendar->tstamp) > 120)) || $force_import) {
                $objCalendar->ical_importing = true;
                $objCalendar->save();

                // create new from ical file
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->info('Reload iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): Triggered by '.time().' - '.$last_change.' = '.(time() - $last_change).' > '.$objCalendar->ical_cache)
                ;

                $startDate = !empty((string) $objCalendar->ical_source_start) ?
                    new Date($objCalendar->ical_source_start, Config::get('dateFormat')) :
                    new Date(time(), Config::get('dateFormat'));
                $endDate = !empty((string) $objCalendar->ical_source_end) ?
                    new Date($objCalendar->ical_source_end, Config::get('dateFormat')) :
                    new Date(time() + $this->defaultEndTimeDifference * 24 * 3600, Config::get('dateFormat'));
                $tz = [$objCalendar->ical_timezone, $objCalendar->ical_timezone];
                $this->importFromWebICS($objCalendar, $startDate, $endDate, $tz);

                $objCalendar->tstamp = time();
                $objCalendar->ical_importing = false;
                $objCalendar->ical_last_sync = time();
                $objCalendar->save();
            }
        }
    }

    /**
     * @param array<mixed>|bool $tz
     *
     * @throws \Exception
     */
    public function importFromIcsFile(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        static::loadDataContainer('tl_calendar_events');

        $schemaManager = $this->db->createSchemaManager();
        $fields = $schemaManager->listTableColumns('tl_calendar_events');

        $fieldNames = [];

        foreach ($fields as $field) {
            if ('id' !== $field->getName()) {
                $fieldNames[] = $field->getName();
            }
        }

        // Get all default values for new entries
        $defaultFields = array_filter($GLOBALS['TL_DCA']['tl_calendar_events']['fields'], static fn ($val) => isset($val['default']));

        $foundevents = [];

        $arrEvents = !empty($objCalendar->id) ? CalendarEventsModel::findByPid($objCalendar->id) ?? [] : [];
        $eventsDictionary = [];

        foreach ($arrEvents as $event) {
            if (empty($event->ical_uuid)) {
                $event->ical_uuid = uniqid('', true);
            }
            $eventsDictionary[$event->ical_uuid] = $event;
        }

        $eventArray = $cal->selectComponents((int) date('Y', (int) $startDate->tstamp), (int) date('m', (int) $startDate->tstamp),
            (int) date('d', (int) $startDate->tstamp), (int) date('Y', (int) $endDate->tstamp), (int) date('m', (int) $endDate->tstamp),
            (int) date('d', (int) $endDate->tstamp), IcalInterface::VEVENT, true);

        if (\is_array($eventArray)) {
            /** @var Vevent $vevent */
            foreach ($eventArray as $vevent) {
                // Use the existing event with matching uuid
                $uid = $vevent->getUid();
                if (isset($eventsDictionary[$uid])) {
                    $objEvent = $eventsDictionary[$uid];
                    unset($eventsDictionary[$uid]);
                } else {
                    $objEvent = new CalendarEventsModel();
                    $objEvent->ical_uuid = $uid;
                }
                $objEvent->tstamp = time();
                $objEvent->pid = $objCalendar->id;
                $objEvent->published = true;

                foreach ($defaultFields as $field => $value) {
                    $varValue = $value['default'];
                    if ($varValue instanceof \Closure) {
                        $varValue = $varValue();
                    }
                    $objEvent->{$field} = $varValue;
                }

                $objEvent->author = BackendUser::getInstance()->id ?? 0;

                /** @var Pc|bool|null $dtstart */
                $dtstart = $vevent->getDtstart(true);
                /** @var Pc|bool|null $dtend */
                $dtend = $vevent->getDtend(true);

                $rrule = $vevent->getRrule();
                $summary = $vevent->getSummary() ?: '---';
                if (!empty($filterEventTitle) && !str_contains(mb_strtolower($summary), mb_strtolower($filterEventTitle))) {
                    continue;
                }
                $description = $vevent->getDescription() ?: '';
                $location = trim($vevent->getLocation() ?: '');

                $title = $summary;
                if (!empty($patternEventTitle) && !empty($replacementEventTitle)) {
                    $title = preg_replace($patternEventTitle, $replacementEventTitle, $summary);
                }

                // set values from vevent
                $objEvent->title = !empty($title) ? $title : $summary;
                $cleanedup = \strlen($description) ? $description : $summary;
                $cleanedup = preg_replace('/[\\r](\\\\)n(\\t){0,1}/ims', '', $cleanedup);
                $cleanedup = preg_replace('/[\\r\\n]/ims', '', (string) $cleanedup);
                $cleanedup = str_replace('\\n', '<br />', (string) $cleanedup);
                $eventcontent = [];

                if (\strlen($cleanedup)) {
                    $eventcontent[] = '<p>'.$cleanedup.'</p>';
                }

                // calendar_events_plus fields
                if (!empty($location)) {
                    if (\in_array('location', $fieldNames, true)) {
                        $location = preg_replace('/(\\\\r)|(\\\\n)/im', "\n", $location);
                        $objEvent->location = $location;
                    } else {
                        $location = preg_replace('/(\\\\r)|(\\\\n)/im', '<br />', $location);
                        $eventcontent[] = '<p><strong>'.$GLOBALS['TL_LANG']['MSC']['location'].':</strong> '.$location.'</p>';
                    }
                }

                if (\in_array('cep_participants', $fieldNames, true) && \is_array($vevent->getAllAttendee())) {
                    $attendees = [];

                    foreach ($vevent->getAllAttendee() as $attendee) {
                        if (!empty($attendee->getParams('CN'))) {
                            $attendees[] = (string) $attendee->getParams('CN');
                        }
                    }

                    if (!empty($attendees)) {
                        $objEvent->cep_participants = implode(',', $attendees);
                    }
                }

                if (\in_array('location_contact', $fieldNames, true)) {
                    $contact = $vevent->getAllContact();
                    $contacts = [];

                    foreach ($contact as $c) {
                        if (!empty($c->getValue())) {
                            $contacts[] = $c->getValue();
                        }
                    }
                    if (!empty($contacts)) {
                        $objEvent->location_contact = implode(',', $contacts);
                    }
                }

                $objEvent->startDate = null;
                $objEvent->startTime = null;
                $objEvent->addTime = false;
                $objEvent->endDate = null;
                $objEvent->endTime = null;
                $timezone = \is_array($tz) ? $tz[1] : null;

                if (!empty($dtstart)) {
                    [$sDate, $timezone] = $this->getDateFromPc($dtstart, $tz[1]);

                    if (!$dtstart->hasParamValue(IcalInterface::DATE)) {
                        $objEvent->addTime = true;
                    } else {
                        $objEvent->addTime = false;
                    }
                    $objEvent->startDate = $sDate->getTimestamp();
                    $objEvent->startTime = $sDate->getTimestamp();
                }
                if (!empty($dtend)) {
                    [$eDate, $timezone] = $this->getDateFromPc($dtend, $tz[1]);

                    if (true === $objEvent->addTime) {
                        $objEvent->endDate = $eDate->getTimestamp();
                        $objEvent->endTime = $eDate->getTimestamp();
                    } else {
                        $endDate = (clone $eDate)->modify('- 1 day')->getTimestamp();
                        $endTime = (clone $eDate)->modify('- 1 second')->getTimestamp();

                        $objEvent->endDate = $endDate;
                        $objEvent->endTime = min($endTime, $endDate);
                    }
                }

                if (0 !== $timeshift) {
                    $objEvent->startDate += $timeshift * 3600;
                    $objEvent->endDate += $timeshift * 3600;
                    $objEvent->startTime += $timeshift * 3600;
                    $objEvent->endTime += $timeshift * 3600;
                }

                if (\is_array($rrule)) {
                    if (
                        \array_key_exists('BYDAY', $rrule)
                        && \is_array($rrule['BYDAY'])
                        && \array_key_exists(0, $rrule['BYDAY'][0])
                        && \array_key_exists('DAY', $rrule['BYDAY'][0])
                        && \in_array('recurringExt', $fieldNames, true)
                    ) {
                        $objEvent->recurringExt = true;
                        $objEvent->recurring = false;

                        $rruleByDay = $rrule['BYDAY'][0];

                        $repeatEachExt = [];
                        $repeatEachExt['value'] = match ($rruleByDay[0]) {
                            1 => 'first',
                            2 => 'second',
                            3 => 'third',
                            4 => 'fourth',
                            5 => 'fifth',
                            default => 'last',
                        };

                        $repeatEachExt['unit'] = match ($rruleByDay['DAY']) {
                            'MO' => 'monday',
                            'TU' => 'tuesday',
                            'WE' => 'wednesday',
                            'TH' => 'thursday',
                            'FR' => 'friday',
                            'SA' => 'saturday',
                            default => 'sunday',
                        };

                        $objEvent->repeatEachExt = serialize($repeatEachExt);
                        $repeatEnd = $this->getRepeatEnd($objEvent, $rrule, $repeatEachExt, $timezone, $timeshift, true);
                    } else {
                        $objEvent->recurring = true;
                        if (\in_array('recurringExt', $fieldNames, true)) {
                            $objEvent->recurringExt = false;
                        }
                        $repeatEach = [];

                        switch ($rrule['FREQ']) {
                            case 'DAILY':
                                $repeatEach['unit'] = 'days';
                                break;
                            case 'WEEKLY':
                                $repeatEach['unit'] = 'weeks';
                                break;
                            case 'MONTHLY':
                                $repeatEach['unit'] = 'months';
                                break;
                            case 'YEARLY':
                                $repeatEach['unit'] = 'years';
                                break;
                        }

                        if (\array_key_exists('BYMONTHDAY', $rrule) && !\array_key_exists('INTERVAL', $rrule)) {
                            $repeatEach['value'] = 1;
                        } else {
                            $repeatEach['value'] = $rrule['INTERVAL'] ?? 1;
                        }
                        $objEvent->repeatEach = serialize($repeatEach);
                        $repeatEnd = $this->getRepeatEnd($objEvent, $rrule, $repeatEach, $timezone, $timeshift);
                    }

                    if (false === $repeatEnd) {
                        $objEvent->repeatEnd = 0;
                        $objEvent->recurring = false;
                        $objEvent->recurringExt = false;
                        $objEvent->recurrences = 0;
                    } else {
                        if (\in_array('repeatWeekday', $fieldNames, true) && isset($rrule['WKST']) && \is_array($rrule['WKST'])) {
                            $weekdays = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];
                            $mapWeekdays = static fn (string $value): int|null => $weekdays[$value] ?? null;
                            $objEvent->repeatWeekday = serialize(array_map($mapWeekdays, $rrule['WKST']));
                        }

                        $recurrences = 0;
                        if (\array_key_exists('COUNT', $rrule)) {
                            $recurrences = ((int) $rrule['COUNT']) - 1;
                        } elseif (\array_key_exists('UNTIL', $rrule)) {
                            $recurrences = $this->calculateRecurrenceCount($objEvent);
                        }
                        $objEvent->recurrences = $recurrences;
                    }
                }
                $this->handleRecurringExceptions($objEvent, $fieldNames, $vevent, $timezone, $timeshift);

                if (!isset($foundevents[$uid])) {
                    $foundevents[$uid] = 0;
                }
                ++$foundevents[$uid];

                if ($foundevents[$uid] <= 1) {
                    if ('' === $objEvent->singleSRC) {
                        $objEvent->singleSRC = null;
                    }

                    $objEvent = $objEvent->save();
                    if (!empty($eventcontent)) {
                        $this->addEventContent($objEvent, $eventcontent);
                    }

                    $this->generateAlias($objEvent);

                    $this->eventDispatcher->dispatch(new AfterImportItemEvent(
                        $objEvent,
                        $vevent,
                        $objCalendar,
                    ));
                }
            }
        }

        if ($deleteCalendar) {
            $this->deleteEvents($eventsDictionary);
        }
    }

    /**
     * @return array<mixed>
     *
     * @throws \Exception
     */
    public function getDateFromPc(Pc $pc, string $tz): array
    {
        if ($pc->hasParamKey(IcalInterface::TZID)) {
            $timezone = $pc->getParams(IcalInterface::TZID);
            $date = $pc->getValue();
        } else {
            if ($pc->getValue()->getTimezone()) {
                $timezone = $pc->getValue()->getTimezone()->getName();
                $date = new \DateTime(
                    $pc->getValue()->format(DateTimeFactory::$YmdHis),
                    $pc->getValue()->getTimezone(),
                );
            } else {
                $timezone = $tz;
                $date = new \DateTime(
                    $pc->getValue()->format(DateTimeFactory::$YmdHis),
                    DateTimeZoneFactory::factory($tz),
                );
            }
        }

        return [$date, $timezone];
    }

    protected function downloadURLToTempFile(string $url, string|null $proxy, string|null $benutzerpw, int|null $port): File|null
    {
        $url = html_entity_decode($url);

        if ($this->isCurlInstalled()) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (!empty($proxy)) {
                curl_setopt($ch, CURLOPT_PROXY, "$proxy");
                if (!empty($benutzerpw)) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$benutzerpw");
                }
                if (!empty($port)) {
                    curl_setopt($ch, CURLOPT_PROXYPORT, $port);
                }
            }

            if (preg_match('/^https/', $url)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $content = curl_exec($ch);
            if (false === $content) {
                $content = null;
            } else {
                $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($responseCode >= 400) {
                    System::getContainer()
                        ->get('monolog.logger.contao.general')
                        ->error('Could not download ics file from URL "'.$url.'". Got response code: '.$responseCode)
                    ;

                    $content = null;
                }
            }
            curl_close($ch);
        } else {
            $content = file_get_contents($url);
        }

        if (empty($content)) {
            System::getContainer()
                ->get('monolog.logger.contao.general')
                ->warning('The downloaded ics file from URL "'.$url.'" seems to be empty.')
            ;

            return null;
        }

        $filename = md5(uniqid((string) random_int(0, mt_getrandmax()), true));
        $objFile = new File('system/tmp/'.$filename);
        $objFile->write($content);
        $objFile->close();

        return $objFile;
    }

    /**
     * @param array<mixed> $timezone
     */
    private function importFromWebICS(CalendarModel $objCalendar, Date $startDate, Date $endDate, array $timezone): void
    {
        if (empty($objCalendar->ical_url)) {
            return;
        }

        $cal = new Vcalendar();
        $cal->setMethod(Vcalendar::PUBLISH);
        $cal->setXprop(Vcalendar::X_WR_CALNAME, $objCalendar->title);
        $cal->setXprop(Vcalendar::X_WR_CALDESC, $objCalendar->title);

        /* start parse of local file */
        $file = $this->downloadURLToTempFile($objCalendar->ical_url, $objCalendar->ical_proxy, $objCalendar->ical_bnpw, $objCalendar->ical_port);
        if (null === $file) {
            return;
        }

        try {
            $cal->parse($file->getContent());
        } catch (\Throwable $e) {
            System::getContainer()
                ->get('monolog.logger.contao.general')
                ->error('Could not import ics file from URL "'.$objCalendar->ical_url.'": '.$e->getMessage())
            ;

            return;
        }

        $tz = $cal->getProperty(IcalInterface::X_WR_TIMEZONE);
        if (false === $tz && !empty($tzComponent = $cal->getComponent(IcalInterface::VTIMEZONE))) {
            $tz = $tzComponent->getXprop(IcalInterface::X_LIC_LOCATION);
        }

        if (!\is_array($tz) || '' === $tz[1]) {
            $tz = $timezone;
        }

        $this->importFromIcsFile($cal, $objCalendar, $startDate, $endDate, $tz, $objCalendar->ical_filter_event_title, $objCalendar->ical_pattern_event_title, $objCalendar->ical_replacement_event_title, true);
    }

    private function isCurlInstalled(): bool
    {
        return \in_array('curl', get_loaded_extensions(), true);
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function calculateRecurrenceCount(CalendarEventsModel $objEvent): int
    {
        if (empty($objEvent->repeatEnd)) {
            return 0;
        }

        if (!empty($objEvent->recurring)) {
            return $this->calculateRecurrenceCountDefault($objEvent);
        }
        if (!empty($objEvent->recurringExt)) {
            return $this->calculateRecurrenceCountExtended($objEvent);
        }

        return 0;
    }

    private function calculateRecurrenceCountDefault(CalendarEventsModel $objEvent): int
    {
        if (empty($objEvent->repeatEnd)) {
            return 0;
        }

        $arrRange = StringUtil::deserialize($objEvent->repeatEach, true);

        if (empty($arrRange) || empty($arrRange['value']) || empty($arrRange['unit'])) {
            return 0;
        }

        $intStart = strtotime(date('Y-m-d', $objEvent->startDate).' '.($objEvent->addTime ? date('H:i', $objEvent->startTime) : '00:00'));
        $intEnd = strtotime(date('Y-m-d', $objEvent->endDate ?: $objEvent->startDate).' '.($objEvent->addTime ? date('H:i', $objEvent->endTime) : '23:59'));

        // store the list of dates
        $next = $intStart;
        $nextEnd = $intEnd;

        $repeatCount = 0;

        // last date of the recurrences
        $end = $objEvent->repeatEnd;

        while ($next <= $end) {
            $timetoadd = '+ '.$arrRange['value'].' '.$arrRange['unit'];
            $next = strtotime($timetoadd, $next);
            $nextEnd = strtotime($timetoadd, $nextEnd);

            // Check if we are at the end
            if (false === $next) {
                break;
            }

            // check if we are at the end
            if ($next >= $end) {
                break;
            }

            $weekday = date('N', $next);
            $arrWeekdays = StringUtil::deserialize($objEvent->repeatWeekday, true);
            if (!empty($arrWeekdays) && 'days' === $arrRange['unit']) {
                if (!\in_array($weekday, $arrWeekdays, true)) {
                    continue;
                }
            }

            if ($objEvent->hideOnWeekend) {
                if ((int) $weekday >= 6) {
                    continue;
                }
            }

            ++$repeatCount;

            // check if we reached the configured max value
            if ($repeatCount === $this->maxRepeatCount) {
                break;
            }
        }

        return $repeatCount;
    }

    private function calculateRecurrenceCountExtended(CalendarEventsModel $objEvent): int
    {
        $repeatCount = 0;
        $arrRange = StringUtil::deserialize($objEvent->repeatEachExt, true);

        if (!empty($arrRange) && !empty($arrRange['value']) && !empty($arrRange['unit'])) {
            $arg = $arrRange['value'];
            $unit = $arrRange['unit'];

            // next month of the event
            $month = (int) date('n', $objEvent->startDate);
            // year of the event
            $year = (int) date('Y', $objEvent->startDate);
            // search date for the next event
            $next = strtotime(date('Y-m-d', $objEvent->startDate).' '.($objEvent->addTime ? date('H:i', $objEvent->startTime) : '00:00'));
            $nextEnd = strtotime(date('Y-m-d', $objEvent->endDate).' '.($objEvent->addTime ? date('H:i', $objEvent->endTime) : '23:59'));

            $end = $objEvent->repeatEnd;

            while ($next <= $end) {
                $timetoadd = $arg.' '.$unit.' of '.$GLOBALS['TL_LANG']['MONTHS'][$month - 1].' '.$year;
                $strtotime = strtotime($timetoadd, $next);

                if (false === $strtotime) {
                    break;
                }

                $next = strtotime(date('Y-m-d', $strtotime).' '.date('H:i', $objEvent->startTime));

                $strtotime = strtotime($timetoadd, $nextEnd);
                $nextEnd = strtotime(date('Y-m-d', $strtotime).' '.date('H:i', $objEvent->endTime));
                ++$repeatCount;

                ++$month;

                if (0 === $month % 13) {
                    $month = 1;
                    ++$year;
                }
            }
        }

        return $repeatCount;
    }

    /**
     * @param array<mixed> $rrule
     * @param array<mixed> $repeatEach
     *
     * @throws \Exception
     */
    private function getRepeatEnd(CalendarEventsModel $objEvent, array $rrule, array $repeatEach, string $timezone, int $timeshift = 0, bool $blnExtended = false): bool|int
    {
        $repeatEnd = $this->getRecurringUntilDate($rrule, $timezone, $timeshift, $objEvent->startDate);
        if (false === $repeatEnd) {
            return false;
        }

        if (!empty($repeatEnd)) {
            return $repeatEnd;
        }

        if (0 === $objEvent->recurrences) {
            return (int) min(4_294_967_295, PHP_INT_MAX);
        }

        if (!$blnExtended && isset($repeatEach['unit'], $repeatEach['value'])) {
            $arg = $repeatEach['value'] * $objEvent->recurrences;
            $unit = $repeatEach['unit'];

            $strtotime = '+ '.$arg.' '.$unit;

            return (int) strtotime($strtotime, $objEvent->endTime);
        }
        if ($blnExtended && isset($repeatEach['unit'], $repeatEach['value'])) {
            $arg = $repeatEach['value'];
            $unit = $repeatEach['unit'];

            $recurrences = $objEvent->recurrences;
            $month = (int) date('n', $objEvent->startDate);
            $year = (int) date('Y', $objEvent->startDate);
            $next = (int) $objEvent->startTime;

            if ($recurrences > 0) {
                for ($i = 0; $i < $recurrences; ++$i) {
                    ++$month;

                    if (0 === $month % 13) {
                        $month = 1;
                        ++$year;
                    }

                    $timetoadd = $arg.' '.$unit.' of '.$this->arrMonths[$month - 1].' '.$year;
                    $strtotime = strtotime($timetoadd, $next);

                    if (false === $strtotime) {
                        break;
                    }

                    $next = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $objEvent->startTime));
                }

                return $next;
            }
            $repeatEnd = min(4294967295, PHP_INT_MAX);
            $i = 0;

            while ($next <= $repeatEnd) {
                $timetoadd = $arg.' '.$unit.' of '.$GLOBALS['TL_LANG']['MONTHS'][$month - 1].' '.$year;
                $strtotime = strtotime($timetoadd, $next);

                if (false === $strtotime) {
                    break;
                }

                $next = strtotime(date('d.m.Y', $strtotime).' '.date('H:i', $objEvent->startTime));

                ++$month;

                if (0 === $month % 13) {
                    $month = 1;
                    ++$year;
                }

                // check if we reached the configured max value
                if (++$i === $this->maxRepeatCount) {
                    return $next;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<mixed> $fieldNames
     * @param Vevent       $vevent
     * @param string       $timezone
     * @param int          $timeshift
     */
    private function handleRecurringExceptions(CalendarEventsModel $objEvent, array $fieldNames, $vevent, $timezone, $timeshift): void
    {
        if (
            !\array_key_exists('useExceptions', $fieldNames)
            && !\array_key_exists('repeatExceptions', $fieldNames)
            && !\array_key_exists('exceptionList', $fieldNames)
        ) {
            return;
        }

        $objEvent->useExceptions = 0;
        $objEvent->repeatExceptions = null;
        $objEvent->exceptionList = null;

        $exDates = [];

        while (false !== ($exDateRow = $vevent->getExdate())) {
            foreach ($exDateRow as $exDate) {
                if ($exDate instanceof \DateTime) {
                    // convert UNTIL date to current timezone
                    $exDate = new \DateTime(
                        $exDate->format(DateTimeFactory::$YmdHis),
                        DateTimeZoneFactory::factory($timezone),
                    );
                    $timestamp = $exDate->getTimestamp();
                    if (0 !== $timeshift) {
                        $timestamp += $timeshift * 3600;
                    }
                    $exDates[$timestamp] = [
                        'exception' => $timestamp,
                        'action' => 'hide',
                    ];
                }
            }
        }

        if (empty($exDates)) {
            return;
        }

        $objEvent->useExceptions = true;
        ksort($exDates);
        $objEvent->exceptionList = $exDates;
        $objEvent->repeatExceptions = array_values($exDates);
    }

    /**
     * @param array<mixed> $rrule
     *
     * @throws \DateMalformedStringException
     */
    private function getRecurringUntilDate(array $rrule, string $timezone, int $timeshift, int $eventStartTime): bool|int|null
    {
        if (($until = $rrule[IcalInterface::UNTIL] ?? null) instanceof \DateTime) {
            // convert UNTIL date to current timezone
            $until = new \DateTime(
                $until->format(DateTimeFactory::$YmdHis),
                DateTimeZoneFactory::factory($timezone),
            );

            $timestamp = $until->getTimestamp();
            if (0 !== $timeshift) {
                $timestamp += $timeshift * 3600;
            }

            if ($timestamp < $eventStartTime) {
                return false;
            }

            return $timestamp;
        }

        return null;
    }
}
