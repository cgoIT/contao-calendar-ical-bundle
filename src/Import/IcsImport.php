<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Cgoit\ContaoCalendarIcalBundle\Event\AfterImportItemEvent;
use Cgoit\ContaoCalendarIcalBundle\Event\BeforeImportItemEvent;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Pc;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Util\DateTimeZoneFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IcsImport extends AbstractImport
{
    private const array MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private const int IMPORT_LOCK_TIMEOUT = 120;

    private int|null $maxRepeatCount = null;

    public function __construct(
        private readonly Connection $db,
        Slug $slug,
        private readonly ContaoFramework $contaoFramework,
        private readonly int $defaultEndTimeDifference,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RemoteIcsDownloader $downloader,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct($slug);
    }

    /**
     * Imports the web source of a calendar if the cache time ("ical_cache") is over or the import is forced.
     * A running import of the same calendar is never started twice in parallel.
     *
     * @return bool True if the import was started (and not skipped because of the cache time or a running import)
     */
    public function importIcsForCalendar(CalendarModel $objCalendar, bool $force_import = false): bool
    {
        if (empty($objCalendar->ical_source) || empty($objCalendar->ical_url)) {
            return false;
        }

        $lastSync = (int) $objCalendar->ical_last_sync;
        $cacheExpired = 0 === $lastSync || (time() - $lastSync) > (int) $objCalendar->ical_cache;

        if (!$force_import && !$cacheExpired) {
            return false;
        }

        $this->contaoFramework->initialize();

        if (!$this->acquireImportLock($objCalendar)) {
            $this->logger->info('Skip reload of iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): The import is already running');

            return false;
        }

        $synced = false;

        try {
            $this->logger->info('Reload iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.')'.($force_import ? ' (forced)' : ''));

            $startDate = !empty((string) $objCalendar->ical_source_start) ?
                new Date($objCalendar->ical_source_start, Config::get('dateFormat')) :
                new Date(time(), Config::get('dateFormat'));
            $endDate = !empty((string) $objCalendar->ical_source_end) ?
                new Date($objCalendar->ical_source_end, Config::get('dateFormat')) :
                new Date(time() + $this->defaultEndTimeDifference * 24 * 3600, Config::get('dateFormat'));
            $tz = [$objCalendar->ical_timezone, $objCalendar->ical_timezone];

            // Without a successful import the next run retries, it does not wait for the cache time
            $synced = $this->importFromWebICS($objCalendar, $startDate, $endDate, $tz);
        } catch (\Throwable $e) {
            $this->logger->error('Could not import iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): '.$e->getMessage());
        } finally {
            $this->releaseImportLock($objCalendar, $synced);
        }

        return true;
    }

    /**
     * Imports the events of the ics file. Everything or nothing is imported, also if the events of
     * the calendar are replaced.
     *
     * @param array<mixed>|bool $tz
     *
     * @throws \Exception
     */
    public function importFromIcsFile(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        $this->db->beginTransaction();

        try {
            $this->importEvents($cal, $objCalendar, $startDate, $endDate, $tz, $filterEventTitle, $patternEventTitle, $replacementEventTitle, $deleteCalendar, $timeshift);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();

            throw $e;
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
        } elseif ($this->isWithoutTimezone($pc)) {
            // Date values and floating times carry no time zone; iCalcreator returns them as UTC.
            // They have to be read as wall-clock time of the calendar.
            $timezone = $tz;
            $date = new \DateTime(
                $pc->getValue()->format(DateTimeFactory::$YmdHis),
                DateTimeZoneFactory::factory($tz),
            );
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

    /**
     * Date values and floating times carry no time zone; iCalcreator returns them as UTC.
     */
    public function isWithoutTimezone(Pc $pc): bool
    {
        return $pc->hasParamValue(IcalInterface::DATE) || $pc->hasParamKey(IcalInterface::ISLOCALTIME);
    }

    /**
     * Calculates "endDate" and "endTime" of a Contao event from the (exclusive) end of an ics event.
     * Contao stores the end of an all day event as 23:59:59 of its last day.
     *
     * @return array{0: int, 1: int}
     */
    public function getEndTimestamps(\DateTimeInterface $end, bool $addTime): array
    {
        if ($addTime) {
            return [$end->getTimestamp(), $end->getTimestamp()];
        }

        // The end of a DATE value is exclusive, so the last day is the day before
        $end = \DateTime::createFromInterface($end);

        return [
            (clone $end)->modify('- 1 day')->getTimestamp(),
            (clone $end)->modify('- 1 second')->getTimestamp(),
        ];
    }

    /**
     * Takes the lock of the import atomically in the database. "tstamp" holds the start of a running
     * import; an older lock is left over from a crashed process and can be taken over.
     */
    protected function acquireImportLock(CalendarModel $objCalendar): bool
    {
        $now = time();

        $affected = $this->db->executeStatement(
            "UPDATE tl_calendar SET ical_importing = '1', tstamp = :now WHERE id = :id AND (ical_importing = '' OR ical_importing IS NULL OR tstamp < :stale)",
            ['now' => $now, 'id' => (int) $objCalendar->id, 'stale' => $now - self::IMPORT_LOCK_TIMEOUT],
        );

        if ($affected < 1) {
            return false;
        }

        $objCalendar->ical_importing = '1';
        $objCalendar->tstamp = $now;

        return true;
    }

    /**
     * Releases the lock and stores the time of the last successful import. Only these columns are written,
     * changes of the calendar settings that were made in the meantime stay untouched.
     */
    protected function releaseImportLock(CalendarModel $objCalendar, bool $synced): void
    {
        $now = time();
        $set = "ical_importing = '', tstamp = :now";
        $params = ['now' => $now, 'id' => (int) $objCalendar->id];

        if ($synced) {
            $set .= ', ical_last_sync = :sync';
            $params['sync'] = $now;
            $objCalendar->ical_last_sync = $now;
        }

        $this->db->executeStatement('UPDATE tl_calendar SET '.$set.' WHERE id = :id', $params);

        $objCalendar->ical_importing = '';
        $objCalendar->tstamp = $now;
    }

    /**
     * @param array<mixed>|bool $tz
     *
     * @throws \Exception
     */
    protected function importEvents(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        $this->contaoFramework->initialize();
        System::loadLanguageFile('default');
        Controller::loadDataContainer('tl_calendar_events');

        // Without a time zone in the ics file (or if none was selected) the time zone of the system applies
        if (!\is_array($tz) || empty($tz[1])) {
            $tz = [Config::get('timeZone'), Config::get('timeZone')];
        }

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

        // Events that change a single recurrence of another event (RECURRENCE-ID) are not part of the selection
        $supportsExceptions = \in_array('useExceptions', $fieldNames, true)
            && \in_array('repeatExceptions', $fieldNames, true)
            && \in_array('exceptionList', $fieldNames, true);
        $components = $this->collectComponents($cal, \is_array($eventArray) ? $eventArray : [], $startDate, $endDate, $tz[1], $supportsExceptions);
        $publish = $this->publishImportedEvents($objCalendar);

        foreach ($components as $uid => [$vevent, $overrides]) {
            // Use the existing event with matching uuid
            $uid = (string) $uid;
            if (isset($eventsDictionary[$uid])) {
                $objEvent = $eventsDictionary[$uid];
                unset($eventsDictionary[$uid]);
            } else {
                $objEvent = new CalendarEventsModel();
                $objEvent->ical_uuid = $uid;
            }

            $beforeEvent = $this->eventDispatcher->dispatch(new BeforeImportItemEvent(
                $objEvent,
                $vevent,
                $objCalendar,
            ));

            if ($beforeEvent->skip) {
                continue;
            }

            $isNew = null === $objEvent->id;
            $objEvent->tstamp = time();
            $objEvent->pid = $objCalendar->id;

            foreach ($defaultFields as $field => $value) {
                $varValue = $value['default'];
                if ($varValue instanceof \Closure) {
                    $varValue = $varValue();
                }
                $objEvent->{$field} = $varValue;
            }

            $published = $this->getPublishedState($publish, $isNew);

            // Events that exist already keep their state if the imported events are not published
            if (null !== $published) {
                $objEvent->published = $published;
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

            $title = $this->rewriteTitle($summary, $patternEventTitle, $replacementEventTitle);

            // set values from vevent
            $objEvent->title = $this->encodeImportedText(!empty($title) ? $title : $summary);
            $cleanedup = $this->encodeImportedText(\strlen($description) ? $description : $summary);
            $cleanedup = preg_replace('/[\\r](\\\\)n(\\t){0,1}/ims', '', $cleanedup);
            $cleanedup = preg_replace('/[\\r\\n]/ims', '', (string) $cleanedup);
            $cleanedup = str_replace('\\n', '<br />', (string) $cleanedup);
            $eventcontent = [];

            if (\strlen($cleanedup)) {
                $eventcontent[] = '<p>'.$cleanedup.'</p>';
            }

            // calendar_events_plus fields
            if (!empty($location)) {
                $location = $this->encodeImportedText($location);

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
                        $attendees[] = $this->encodeImportedText((string) $attendee->getParams('CN'));
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
                        $contacts[] = $this->encodeImportedText((string) $c->getValue());
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
            $timezone = $tz[1];

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

                [$objEvent->endDate, $objEvent->endTime] = $this->getEndTimestamps($eDate, (bool) $objEvent->addTime);
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
                    $repeatEndEach = $repeatEachExt;
                    $repeatEndExtended = true;
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

                    if (
                        \array_key_exists('BYDAY', $rrule)
                        && \is_array($rrule['BYDAY'])
                        && \in_array('repeatWeekday', $fieldNames, true)
                    ) {
                        $weekdays = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];
                        $mapWeekdays = static fn (array $value): int|null => \array_key_exists('DAY', $value)
                            ? $weekdays[$value['DAY']] ?? null
                            : null;
                        $objEvent->repeatWeekday = serialize(array_map($mapWeekdays, $rrule['BYDAY']));
                    }

                    if (\array_key_exists('BYMONTHDAY', $rrule) && !\array_key_exists('INTERVAL', $rrule)) {
                        $repeatEach['value'] = 1;
                    } else {
                        $repeatEach['value'] = $rrule['INTERVAL'] ?? 1;
                    }
                    $objEvent->repeatEach = serialize($repeatEach);
                    $repeatEndEach = $repeatEach;
                    $repeatEndExtended = false;
                }

                // UNTIL is a UTC time, except for date values and floating times where it is a local time, too
                $untilIsLocal = !empty($dtstart) && $this->isWithoutTimezone($dtstart);

                $this->applyRecurrenceEnd($objEvent, $rrule, $repeatEndEach, $timezone, $timeshift, $repeatEndExtended, $untilIsLocal);
            }
            $this->handleRecurringExceptions($objEvent, $fieldNames, $vevent, $timezone, $timeshift, $overrides);

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

        if ($deleteCalendar) {
            $this->deleteEvents($eventsDictionary);
        }
    }

    /**
     * Sets "recurrences" and "repeatEnd" of the event from COUNT/UNTIL of the rule. The number of
     * recurrences has to be known before the end is calculated.
     *
     * @param array<mixed> $rrule
     * @param array<mixed> $repeatEach
     *
     * @throws \Exception
     */
    protected function applyRecurrenceEnd(CalendarEventsModel $objEvent, array $rrule, array $repeatEach, string $timezone, int $timeshift, bool $blnExtended, bool $untilIsLocal = false): void
    {
        // A series with a single occurrence is no recurring event; 0 recurrences would mean "unlimited"
        if (\array_key_exists('COUNT', $rrule) && (int) $rrule['COUNT'] <= 1) {
            $objEvent->repeatEnd = 0;
            $objEvent->recurring = false;
            $objEvent->recurringExt = false;
            $objEvent->recurrences = 0;

            return;
        }

        // 0 means "unlimited", an UNTIL date is converted into a count below
        $objEvent->recurrences = \array_key_exists('COUNT', $rrule) ? ((int) $rrule['COUNT']) - 1 : 0;

        $repeatEnd = $this->getRepeatEnd($objEvent, $rrule, $repeatEach, $timezone, $timeshift, $blnExtended, $untilIsLocal);

        if (false === $repeatEnd) {
            $objEvent->repeatEnd = 0;
            $objEvent->recurring = false;
            $objEvent->recurringExt = false;
            $objEvent->recurrences = 0;

            return;
        }

        $objEvent->repeatEnd = $repeatEnd;

        if (!\array_key_exists('COUNT', $rrule) && \array_key_exists('UNTIL', $rrule)) {
            $objEvent->recurrences = $this->calculateRecurrenceCount($objEvent);
        }
    }

    /**
     * The maximum number of recurrences, configured by the calendar-extended-bundle if it is installed.
     */
    private function getMaxRepeatCount(): int
    {
        $this->maxRepeatCount ??= $this->parameterBag->has('cgoit_calendar_extended.max_repeat_count')
            ? (int) $this->parameterBag->get('cgoit_calendar_extended.max_repeat_count')
            : 365;

        return $this->maxRepeatCount;
    }

    /**
     * Rewrites the title with the configured regular expression. An invalid expression or a
     * failing replacement (e.g. backtrack limit) leaves the title untouched.
     */
    private function rewriteTitle(string $summary, string|null $pattern, string|null $replacement): string
    {
        if (empty($pattern) || empty($replacement)) {
            return $summary;
        }

        $title = @preg_replace($pattern, $replacement, $summary);

        if (!\is_string($title)) {
            $this->logger->warning('Could not rewrite the title "'.$summary.'" with the pattern "'.$pattern.'": '.preg_last_error_msg());

            return $summary;
        }

        return $title;
    }

    /**
     * @param array<mixed> $timezone
     *
     * @return bool True if the source could be downloaded and parsed and contains events
     */
    private function importFromWebICS(CalendarModel $objCalendar, Date $startDate, Date $endDate, array $timezone): bool
    {
        if (empty($objCalendar->ical_url)) {
            return false;
        }

        $cal = new Vcalendar();
        $cal->setMethod(Vcalendar::PUBLISH);
        $cal->setXprop(Vcalendar::X_WR_CALNAME, $objCalendar->title);
        $cal->setXprop(Vcalendar::X_WR_CALDESC, $objCalendar->title);

        $content = $this->downloader->download($objCalendar->ical_url, $objCalendar->ical_proxy, $objCalendar->ical_bnpw, $objCalendar->ical_port ? (int) $objCalendar->ical_port : null);
        if (null === $content) {
            return false;
        }

        try {
            $cal->parse($content);
        } catch (\Throwable $e) {
            $this->logger->error('Could not import ics file from URL "'.$objCalendar->ical_url.'": '.$e->getMessage());

            return false;
        }

        // A feed without any event is more likely an error of the source than an emptied calendar; the events stay
        if ([] === $cal->getComponents(IcalInterface::VEVENT)) {
            $this->logger->warning('Skip the import of the iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): The source "'.$objCalendar->ical_url.'" contains no events, the existing events are kept');

            return false;
        }

        $tz = $cal->getProperty(IcalInterface::X_WR_TIMEZONE);
        if (false === $tz && !empty($tzComponent = $cal->getComponent(IcalInterface::VTIMEZONE))) {
            $tz = $tzComponent->getXprop(IcalInterface::X_LIC_LOCATION);
        }

        if (!\is_array($tz) || '' === $tz[1]) {
            $tz = $timezone;
        }

        $this->importFromIcsFile($cal, $objCalendar, $startDate, $endDate, $tz, $objCalendar->ical_filter_event_title, $objCalendar->ical_pattern_event_title, $objCalendar->ical_replacement_event_title, true);

        return true;
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

            // the recurrence on the last day (UNTIL is inclusive) is counted
            if ($next > $end) {
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
            if ($repeatCount === $this->getMaxRepeatCount()) {
                break;
            }
        }

        if (0 === $repeatCount) {
            // in this case the end date is before the first recurrence
            // we need to set the event to non-recurring as 0 would mean that the event recurs infinitely
            $objEvent->recurring = false;
        }

        return $repeatCount;
    }

    /**
     * Counts the recurrences after the first event up to (and including) the repeat end.
     */
    private function calculateRecurrenceCountExtended(CalendarEventsModel $objEvent): int
    {
        $repeatCount = 0;
        $arrRange = StringUtil::deserialize($objEvent->repeatEachExt, true);

        if (empty($arrRange) || empty($arrRange['value']) || empty($arrRange['unit'])) {
            return 0;
        }

        $arg = $arrRange['value'];
        $unit = $arrRange['unit'];
        $time = $objEvent->addTime ? date('H:i', $objEvent->startTime) : '00:00';

        $month = (int) date('n', $objEvent->startDate);
        $year = (int) date('Y', $objEvent->startDate);
        $start = (int) strtotime(date('Y-m-d', $objEvent->startDate).' '.$time);
        $end = $objEvent->repeatEnd;

        // The year limit protects against endless loops
        while ($year <= 2200) {
            $strtotime = strtotime($arg.' '.$unit.' of '.self::MONTHS[$month - 1].' '.$year, $start);

            if (false === $strtotime) {
                break;
            }

            if (++$month > 12) {
                $month = 1;
                ++$year;
            }

            $next = (int) strtotime(date('Y-m-d', $strtotime).' '.$time);

            // the first event itself (or a date before it) is no recurrence
            if ($next <= $start) {
                continue;
            }

            if ($next > $end) {
                break;
            }

            ++$repeatCount;

            // check if we reached the configured max value
            if ($repeatCount === $this->getMaxRepeatCount()) {
                break;
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
    private function getRepeatEnd(CalendarEventsModel $objEvent, array $rrule, array $repeatEach, string $timezone, int $timeshift = 0, bool $blnExtended = false, bool $untilIsLocal = false): bool|int
    {
        $repeatEnd = $this->getRecurringUntilDate($rrule, $timezone, $timeshift, $objEvent->startDate, $untilIsLocal);
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

                    $timetoadd = $arg.' '.$unit.' of '.self::MONTHS[$month - 1].' '.$year;
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
                $timetoadd = $arg.' '.$unit.' of '.self::MONTHS[$month - 1].' '.$year;
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
                if (++$i === $this->getMaxRepeatCount()) {
                    return $next;
                }
            }
        }

        return 0;
    }

    /**
     * Collects the events to import: one per UID (the selection of the period never contains events with a
     * RECURRENCE-ID) plus their overrides. An override changes a single recurrence of a recurring event.
     * With the exceptions of the calendar-extended-bundle it is added to the event as a "move" exception.
     * Otherwise (or if the event of the override is not part of the period) it is imported as an event of its own,
     * identified by UID and RECURRENCE-ID.
     *
     * @param list<Vevent> $events The events of the period
     *
     * @return array<string, array{0: Vevent, 1: list<Vevent>}> The event and its overrides by the key of the event
     *
     * @throws \Exception
     */
    private function collectComponents(Vcalendar $cal, array $events, Date $startDate, Date $endDate, string $timezone, bool $supportsExceptions): array
    {
        $items = [];

        foreach ($events as $vevent) {
            $items[(string) $vevent->getUid()] = [$vevent, []];
        }

        $overrides = [];

        foreach ($cal->getComponents(IcalInterface::VEVENT) as $component) {
            if (!$component instanceof Vevent) {
                continue;
            }

            if (false !== $component->getRecurrenceid(true)) {
                $overrides[] = $component;
            }
        }

        foreach ($overrides as $override) {
            $uid = (string) $override->getUid();

            if ($supportsExceptions && isset($items[$uid])) {
                $items[$uid][1][] = $override;

                continue;
            }

            if ('CANCELLED' === $override->getStatus() || !$this->isInPeriod($override, $startDate, $endDate, $timezone)) {
                continue;
            }

            $recurrenceId = $override->getRecurrenceid(true);
            $key = $uid.'#'.\DateTimeImmutable::createFromInterface($recurrenceId->getValue())->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis');

            // The key is stored in a column of 255 characters
            $items[\strlen($key) > 255 ? substr($uid, 0, 200).'#'.md5($key) : $key] = [$override, []];
        }

        return $items;
    }

    /**
     * @throws \Exception
     */
    private function isInPeriod(Vevent $vevent, Date $startDate, Date $endDate, string $timezone): bool
    {
        $dtstart = $vevent->getDtstart(true);

        if (!$dtstart instanceof Pc) {
            return false;
        }

        $dtend = $vevent->getDtend(true);
        $start = $this->getDateFromPc($dtstart, $timezone)[0]->getTimestamp();
        $end = $dtend instanceof Pc ? $this->getDateFromPc($dtend, $timezone)[0]->getTimestamp() : $start;

        $from = (int) strtotime(date('Y-m-d', (int) $startDate->tstamp).' 00:00:00');
        $to = (int) strtotime(date('Y-m-d', (int) $endDate->tstamp).' 23:59:59');

        return $start <= $to && $end >= $from;
    }

    /**
     * @param array<mixed> $fieldNames
     * @param list<Vevent> $overrides  The events that change a single recurrence of the event
     *
     * @throws \Exception
     */
    private function handleRecurringExceptions(CalendarEventsModel $objEvent, array $fieldNames, Vevent $vevent, string $timezone, int $timeshift, array $overrides = []): void
    {
        if (
            !\in_array('useExceptions', $fieldNames, true)
            || !\in_array('repeatExceptions', $fieldNames, true)
            || !\in_array('exceptionList', $fieldNames, true)
        ) {
            return;
        }

        $objEvent->useExceptions = 0;
        $objEvent->repeatExceptions = null;
        $objEvent->exceptionList = null;

        $exDates = [];

        // getAllExdate() fails for an event that never had an EXDATE
        foreach (false === $vevent->getExdate() ? [] : $vevent->getAllExdate(true) as $exDateRow) {
            // Dates and floating times are local times; all others (UTC or with a time zone) are exact points in time
            $isLocal = $this->isWithoutTimezone($exDateRow);

            foreach ((array) $exDateRow->getValue() as $exDate) {
                if (!$exDate instanceof \DateTimeInterface) {
                    continue;
                }

                if ($isLocal) {
                    $exDate = new \DateTime(
                        $exDate->format(DateTimeFactory::$YmdHis),
                        DateTimeZoneFactory::factory($timezone),
                    );
                }

                $timestamp = $exDate->getTimestamp() + $timeshift * 3600;
                $exDates[$timestamp] = [
                    'exception' => $timestamp,
                    'action' => 'hide',
                ];
            }
        }

        foreach ($overrides as $override) {
            $exception = $this->createException($objEvent, $override, $timezone, $timeshift);

            if (null !== $exception) {
                $exDates[$exception['exception']] = $exception;
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
     * Creates the exception of the calendar-extended-bundle for an override: a cancelled recurrence is hidden,
     * a changed one is moved by a number of days (and gets new times if they differ).
     *
     * @return array<string, mixed>|null Null if the override does not change the recurrence
     *
     * @throws \Exception
     */
    private function createException(CalendarEventsModel $objEvent, Vevent $override, string $timezone, int $timeshift): array|null
    {
        $recurrenceId = $override->getRecurrenceid(true);
        $dtstart = $override->getDtstart(true);

        if (!$recurrenceId instanceof Pc || !$dtstart instanceof Pc) {
            return null;
        }

        $zone = DateTimeZoneFactory::factory($timezone);
        $original = \DateTimeImmutable::createFromInterface($this->getDateFromPc($recurrenceId, $timezone)[0])->setTimezone($zone);
        $exception = ['exception' => $original->getTimestamp() + $timeshift * 3600];

        if ('CANCELLED' === $override->getStatus()) {
            return $exception + ['action' => 'hide'];
        }

        $start = \DateTimeImmutable::createFromInterface($this->getDateFromPc($dtstart, $timezone)[0])->setTimezone($zone);
        $shift = $timeshift * 3600;
        $dtend = $override->getDtend(true);
        $duration = max(0, (int) $objEvent->endTime - (int) $objEvent->startTime);
        $end = $dtend instanceof Pc
            ? \DateTimeImmutable::createFromInterface($this->getDateFromPc($dtend, $timezone)[0])->setTimezone($zone)
            : $start->modify('+'.$duration.' seconds');

        $days = (int) $original->setTime(0, 0)->diff($start->setTime(0, 0))->format('%r%a');
        $exception += ['action' => 'move', 'new_exception' => \sprintf('%+d days', $days)];

        // Different times need to be given explicitly, otherwise the time of the recurrence is kept
        if (
            !empty($objEvent->addTime)
            && ($original->format('H:i') !== $start->format('H:i') || $end->getTimestamp() - $start->getTimestamp() !== $duration)
        ) {
            $exception += ['new_start' => $start->modify($shift.' seconds')->format('H:i'), 'new_end' => $end->modify($shift.' seconds')->format('H:i')];
        }

        return 0 === $days && !isset($exception['new_start']) ? null : $exception;
    }

    /**
     * Many feeds give UNTIL as a date (UNTIL=20240531) although the event has a time. Such a value is read as
     * midnight and would exclude the recurrence of the last day, so it is moved to the end of that day.
     * A UNTIL that is exactly midnight of the event time zone is interpreted this way, too.
     */
    private function extendDateOnlyUntil(\DateTime $until, string $timezone, int $eventStartTime): \DateTime
    {
        $zone = DateTimeZoneFactory::factory($timezone);
        $local = \DateTime::createFromInterface($until)->setTimezone($zone);
        $startsAtMidnight = '000000' === (new \DateTimeImmutable('@'.$eventStartTime))->setTimezone($zone)->format('His');

        if ('000000' === $local->format('His') && !$startsAtMidnight) {
            return $local->setTime(23, 59, 59);
        }

        return $until;
    }

    /**
     * Returns the time stamp of the UNTIL value. It is an exact point in time (UTC) unless the event starts with a
     * date or a floating time; then the value is a local time and is read in the given time zone.
     *
     * @param array<mixed> $rrule
     *
     * @throws \DateMalformedStringException
     */
    private function getRecurringUntilDate(array $rrule, string $timezone, int $timeshift, int $eventStartTime, bool $untilIsLocal = false): bool|int|null
    {
        if (($until = $rrule[IcalInterface::UNTIL] ?? null) instanceof \DateTime) {
            if ($untilIsLocal) {
                $until = new \DateTime(
                    $until->format(DateTimeFactory::$YmdHis),
                    DateTimeZoneFactory::factory($timezone),
                );
            }

            $until = $this->extendDateOnlyUntil($until, $timezone, $eventStartTime);

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
