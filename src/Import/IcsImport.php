<?php

declare(strict_types=1);

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\File;
use Contao\System;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Pc;
use Kigkonsult\Icalcreator\Util\DateTimeFactory;
use Kigkonsult\Icalcreator\Util\DateTimeZoneFactory;
use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class IcsImport extends AbstractImport
{
    private string $filterEventTitle = '';

    private string $patternEventTitle = '';

    private string $replacementEventTitle = '';

    public function __construct(
        private readonly Connection $db,
        Slug $slug,
    ) {
        parent::__construct($slug);
    }

    public function importIcsForCalendar(CalendarModel $objCalendar, bool $force_import = false): void
    {
        if (!empty($objCalendar->ical_source)) {
            $arrLastchange = $this->db->executeQuery('SELECT MAX(tstamp) lastchange FROM tl_calendar_events WHERE pid = ?', [$objCalendar->id])
                ->fetchAssociative()
            ;

            $last_change = $arrLastchange['lastchange'];

            if (0 === $last_change) {
                $last_change = $objCalendar->tstamp;
            }

            if (((time() - $last_change > $objCalendar->ical_cache) && (1 !== $objCalendar->ical_importing || (time() - $objCalendar->tstamp) > 120)) || $force_import) {
                $this->db->update('tl_calendar', ['tstamp' => time(), 'ical_importing' => '1'], ['id' => $objCalendar->id]);

                // create new from ical file
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('Reload iCal Web Calendar '.$objCalendar->title.' ('.$objCalendar->id.'): Triggered by '.time().' - '.$last_change.' = '.(time() - $arrLastchange['lastchange']).' > '.$objCalendar->ical_cache)
                ;

                $startDate = !empty((string) $objCalendar->ical_source_start) ?
                    new Date($objCalendar->ical_source_start, Config::get('dateFormat')) :
                    new Date(time(), Config::get('dateFormat'));
                $endDate = !empty((string) $objCalendar->ical_source_end) ?
                    new Date($objCalendar->ical_source_end, Config::get('dateFormat')) :
                    new Date(time() + $GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] * 24 * 3600, Config::get('dateFormat'));
                $tz = [$objCalendar->ical_timezone, $objCalendar->ical_timezone];
                $this->filterEventTitle = $objCalendar->ical_filter_event_title;
                $this->patternEventTitle = $objCalendar->ical_pattern_event_title;
                $this->replacementEventTitle = $objCalendar->ical_replacement_event_title;
                $this->importFromWebICS($objCalendar->id, $objCalendar->ical_url, $startDate,
                    $endDate, $tz, $objCalendar->ical_proxy, $objCalendar->ical_bnpw,
                    $objCalendar->ical_port);
                $this->db->update('tl_calendar', ['tstamp' => time(), 'ical_importing' => ''], ['id' => $objCalendar->id]);
            }
        }
    }

    /**
     * @param array<mixed>|bool $tz
     *
     * @throws \Exception
     */
    public function importFromIcsFile(Vcalendar $cal, int $pid, Date $startDate, Date $endDate, array|bool $tz, bool|null $correctTimezone, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        // TODO check what has to be done with $correctTimezone parameter
        // $this->cal->sort() was previously in the code. This is quite useless because without arguments this methods
        // sorts by UID which doesn't give us any benefit.
        // $this->cal->sort();
        static::loadDataContainer('tl_calendar_events');

        $schemaManager = $this->db->createSchemaManager();
        $fields = $schemaManager->listTableColumns('tl_calendar_events');

        $fieldNames = [];
        $arrFields = [];
        $defaultFields = [];

        foreach ($fields as $field) {
            if ('id' !== $field->getName()) {
                $fieldNames[] = $field->getName();
            }
        }

        // Get all default values for new entries
        foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] as $k => $v) {
            if (isset($v['default'])) {
                $defaultFields[$k] = \is_array($v['default']) ? serialize($v['default']) : $v['default'];
            }
        }

        $this->import('BackendUser', 'User');
        $foundevents = [];

        if ($deleteCalendar && $pid) {
            $arrEvents = CalendarEventsModel::findByPid($pid);
            if (!empty($arrEvents)) {
                foreach ($arrEvents as $event) {
                    $arrColumns = ['ptable=? AND pid=?'];
                    $arrValues = ['tl_calendar_events', $event->id];
                    $content = ContentModel::findBy($arrColumns, $arrValues);

                    if ($content) {
                        while ($content->next()) {
                            $content->delete();
                        }
                    }

                    $event->delete();
                }
            }
        }

        $eventArray = $cal->selectComponents((int) date('Y', $startDate->tstamp), (int) date('m', $startDate->tstamp),
            (int) date('d', $startDate->tstamp), (int) date('Y', $endDate->tstamp), (int) date('m', $endDate->tstamp),
            (int) date('d', $endDate->tstamp), 'vevent', true);

        if (\is_array($eventArray)) {
            foreach ($eventArray as $vevent) {
                /** @var Vevent $vevent */
                $arrFields = $defaultFields;
                $dtstart = $vevent->getDtstart();
                /** @var Pc|null $dtstartRow */
                $dtstartRow = $vevent->getDtstart(true);
                $dtend = $vevent->getDtend();
                /** @var Pc|null $dtendRow */
                $dtendRow = $vevent->getDtend(true);
                $rrule = $vevent->getRrule();
                $summary = $vevent->getSummary() ?? '';
                if (!empty($this->filterEventTitle) && !str_contains($summary, (string) $this->filterEventTitle)) {
                    continue;
                }
                $description = $vevent->getDescription() ?? '';
                $location = trim($vevent->getLocation() ?? '');
                $uid = $vevent->getUid();

                $arrFields['tstamp'] = time();
                $arrFields['pid'] = $pid;
                $arrFields['published'] = 1;
                $arrFields['author'] = $this->User->id ?: 0;

                $title = $summary;
                if (!empty($this->patternEventTitle) && !empty($this->replacementEventTitle)) {
                    $title = preg_replace($this->patternEventTitle, (string) $this->replacementEventTitle, $summary);
                }

                // set values from vevent
                $arrFields['title'] = !empty($title) ? $title : $summary;
                $cleanedup = \strlen($description) ? $description : $summary;
                $cleanedup = preg_replace('/[\\r](\\\\)n(\\t){0,1}/ims', '', $cleanedup);
                $cleanedup = preg_replace('/[\\r\\n]/ims', '', $cleanedup);
                $cleanedup = str_replace('\\n', '<br />', $cleanedup);
                $eventcontent = [];

                if (\strlen($cleanedup)) {
                    $eventcontent[] = '<p>'.$cleanedup.'</p>';
                }

                // calendar_events_plus fields
                if (!empty($location)) {
                    if (\in_array('location', $fieldNames, true)) {
                        $location = preg_replace('/(\\\\r)|(\\\\n)/im', "\n", $location);
                        $arrFields['location'] = $location;
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

                    if (\count($attendees)) {
                        $arrFields['cep_participants'] = implode(',', $attendees);
                    }
                }

                if (\in_array('location_contact', $fieldNames, true)) {
                    $contact = $vevent->getContact();
                    if (\is_array($contact)) {
                        $contacts = [];

                        foreach ($contact as $data) {
                            if (!empty($data['value'])) {
                                $contacts[] = $data['value'];
                            }
                        }
                        if (\count($contacts)) {
                            $arrFields['location_contact'] = implode(',', $contacts);
                        }
                    }
                }

                $arrFields['startDate'] = 0;
                $arrFields['startTime'] = 0;
                $arrFields['addTime'] = '';
                $arrFields['endDate'] = 0;
                $arrFields['endTime'] = 0;
                $timezone = \is_array($tz) ? $tz[1] : null;

                if ($dtstart instanceof \DateTime) {
                    if ($dtstartRow instanceof Pc) {
                        if ($dtstartRow->hasParamKey(IcalInterface::TZID)) {
                            $timezone = $dtstartRow->getParams(IcalInterface::TZID);
                        } else {
                            if ($dtstart->getTimezone() && $dtstart->getTimezone()->getName() === $tz[1]) {
                                $timezone = $dtstart->getTimezone()->getName();
                                $dtstart = new \DateTime(
                                    $dtstart->format(DateTimeFactory::$YmdHis),
                                    $dtstart->getTimezone(),
                                );
                            } else {
                                $dtstart = new \DateTime(
                                    $dtstart->format(DateTimeFactory::$YmdHis),
                                    DateTimeZoneFactory::factory($tz[1]),
                                );
                            }
                        }

                        if (!$dtstartRow->hasParamValue(IcalInterface::DATE)) {
                            $arrFields['addTime'] = 1;
                        } else {
                            $arrFields['addTime'] = 0;
                        }
                    } else {
                        if ($dtstart->getTimezone() && $dtstart->getTimezone()->getName() === $tz[1]) {
                            $timezone = $dtstart->getTimezone()->getName();
                            $dtstart = new \DateTime(
                                $dtstart->format(DateTimeFactory::$YmdHis),
                                $dtstart->getTimezone(),
                            );
                        } else {
                            $dtstart = new \DateTime(
                                $dtstart->format(DateTimeFactory::$YmdHis),
                                DateTimeZoneFactory::factory($tz[1]),
                            );
                        }

                        if (!empty($dtstartRow->getParams('VALUE')) && 'DATE' === $dtstartRow->getParams('VALUE')) {
                            $arrFields['addTime'] = 0;
                        } else {
                            $arrFields['addTime'] = 1;
                        }
                    }
                    $arrFields['startDate'] = $dtstart->getTimestamp();
                    $arrFields['startTime'] = $dtstart->getTimestamp();
                }
                if ($dtend instanceof \DateTime) {
                    if ($dtendRow instanceof Pc) {
                        if ($dtendRow->hasParamKey(IcalInterface::TZID)) {
                            $timezone = $dtendRow->getParams(IcalInterface::TZID);
                        } else {
                            if ($dtend->getTimezone() && $dtend->getTimezone()->getName() === $tz[1]) {
                                $timezone = $dtend->getTimezone()->getName();
                                $dtend = new \DateTime(
                                    $dtend->format(DateTimeFactory::$YmdHis),
                                    $dtend->getTimezone(),
                                );
                            } else {
                                $dtend = new \DateTime(
                                    $dtend->format(DateTimeFactory::$YmdHis),
                                    DateTimeZoneFactory::factory($tz[1]),
                                );
                            }
                        }

                        if (1 === $arrFields['addTime']) {
                            $arrFields['endDate'] = $dtend->getTimestamp();
                            $arrFields['endTime'] = $dtend->getTimestamp();
                        } else {
                            $endDate = (clone $dtend)->modify('- 1 day')->getTimestamp();
                            $endTime = (clone $dtend)->modify('- 1 second')->getTimestamp();

                            $arrFields['endDate'] = $endDate;
                            $arrFields['endTime'] = $endTime <= $endDate ? $endTime : $endDate;
                        }
                    } else {
                        if ($dtend->getTimezone() && $dtend->getTimezone()->getName() === $tz[1]) {
                            $timezone = $dtend->getTimezone()->getName();
                            $dtend = new \DateTime(
                                $dtend->format(DateTimeFactory::$YmdHis),
                                $dtend->getTimezone(),
                            );
                        } else {
                            $dtend = new \DateTime(
                                $dtend->format(DateTimeFactory::$YmdHis),
                                DateTimeZoneFactory::factory($tz[1]),
                            );
                        }

                        if (1 === $arrFields['addTime']) {
                            $arrFields['endDate'] = $dtend->getTimestamp();
                            $arrFields['endTime'] = $dtend->getTimestamp();
                        } else {
                            $endDate = (clone $dtend)->modify('- 1 day')->getTimestamp();
                            $endTime = (clone $dtend)->modify('- 1 second')->getTimestamp();

                            $arrFields['endDate'] = $endDate;
                            $arrFields['endTime'] = $endTime <= $endDate ? $endTime : $endDate;
                        }
                    }
                }

                if (0 !== $timeshift) {
                    $arrFields['startDate'] += $timeshift * 3600;
                    $arrFields['endDate'] += $timeshift * 3600;
                    $arrFields['startTime'] += $timeshift * 3600;
                    $arrFields['endTime'] += $timeshift * 3600;
                }

                if (\is_array($rrule)) {
                    $arrFields['recurring'] = 1;
                    $arrFields['recurrences'] = \array_key_exists('COUNT', $rrule) ? $rrule['COUNT'] : 0;
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

                    $repeatEach['value'] = $rrule['INTERVAL'] ?? 1;
                    $arrFields['repeatEach'] = serialize($repeatEach);
                    $arrFields['repeatEnd'] = $this->getRepeatEnd($arrFields, $rrule, $repeatEach, $timezone, $timeshift);

                    if (isset($rrule['WKST']) && \is_array($rrule['WKST'])) {
                        $weekdays = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 0];
                        $mapWeekdays = static fn (string $value): ?int => $weekdays[$value] ?? null;
                        $arrFields['repeatWeekday'] = serialize(array_map($mapWeekdays, $rrule['WKST']));
                    }
                }
                $this->handleRecurringExceptions($arrFields, $vevent, $timezone, $timeshift);

                if (!isset($foundevents[$uid])) {
                    $foundevents[$uid] = 0;
                }
                ++$foundevents[$uid];

                $arrFields['description'] = $uid;

                if ($foundevents[$uid] <= 1) {
                    if (\array_key_exists('singleSRC', $arrFields) && '' === $arrFields['singleSRC']) {
                        $arrFields['singleSRC'] = null;
                    }

                    if ($this->db->insert('tl_calendar_events', $arrFields)) {
                        $insertID = $this->db->lastInsertId();

                        if (\count($eventcontent)) {
                            $step = 128;

                            foreach ($eventcontent as $content) {
                                $cm = new ContentModel();
                                $cm->tstamp = time();
                                $cm->pid = $insertID;
                                $cm->ptable = 'tl_calendar_events';
                                $cm->sorting = $step;
                                $step *= 2;
                                $cm->type = 'text';
                                $cm->text = $content;
                                $cm->save();
                            }
                        }

                        $alias = $this->generateAlias($arrFields['title'], $insertID, $pid);
                        $this->db->update('tl_calendar_events', ['alias' => $alias], ['id' => $insertID]);
                    }
                }
            }
        }
    }

    protected function downloadURLToTempFile(string $url, string $proxy, string $benutzerpw, int $port): File|null
    {
        $url = html_entity_decode((string) $url);

        if ($this->isCurlInstalled()) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            if (!empty($proxy)) {
                curl_setopt($ch, CURLOPT_PROXY, "$proxy");
                if (!empty($benutzerpw)) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$benutzerpw");
                }
                curl_setopt($ch, CURLOPT_PROXYPORT, "$port");
            }

            if (preg_match('/^https/', $url)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $content = curl_exec($ch);
            if (false === $content) {
                $content = null;
            } else {
                $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($responseCode >= 400) {
                    $content = null;
                }
            }
            curl_close($ch);
        } else {
            $content = file_get_contents($url);
        }

        if (empty($content)) {
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
    private function importFromWebICS(int $pid, string $url, Date $startDate, Date $endDate, array $timezone, string $proxy, string $benutzerpw, int $port): void
    {
        $cal = new Vcalendar();
        $cal->setMethod(Vcalendar::PUBLISH);
        $cal->setXprop(Vcalendar::X_WR_CALNAME, $this->strTitle);
        $cal->setXprop(Vcalendar::X_WR_CALDESC, $this->strTitle);

        /* start parse of local file */
        $file = $this->downloadURLToTempFile($url, $proxy, $benutzerpw, $port);
        if (null === $file) {
            return;
        }

        try {
            $cal->parse($file->getContent());
        } catch (\Exception $e) {
            System::getContainer()
                ->get('monolog.logger.contao.general')
                ->error($e->getMessage())
            ;

            return;
        }
        $tz = $cal->getProperty(Vcalendar::X_WR_TIMEZONE);

        if (!\is_array($tz) || '' === $tz[1]) {
            $tz = $timezone;
        }

        $this->importFromIcsFile($cal, $pid, $startDate, $endDate, $tz, null, true);
    }

    private function isCurlInstalled(): bool
    {
        return \in_array('curl', get_loaded_extensions(), true);
    }

    /**
     * @param array<mixed> $arrFields
     * @param array<mixed> $rrule
     * @param array<mixed> $repeatEach
     *
     * @throws \Exception
     */
    private function getRepeatEnd(array $arrFields, array $rrule, array $repeatEach, string $timezone, int $timeshift = 0): int
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

            return $timestamp;
        }

        if (0 === (int) $arrFields['recurrences']) {
            return (int) min(4_294_967_295, PHP_INT_MAX);
        }

        if (isset($repeatEach['unit'], $repeatEach['value'])) {
            $arg = $repeatEach['value'] * $arrFields['recurrences'];
            $unit = $repeatEach['unit'];

            $strtotime = '+ '.$arg.' '.$unit;

            return (int) strtotime($strtotime, $arrFields['endTime']);
        }

        return 0;
    }

    /**
     * @param array  $arrFields
     * @param Vevent $vevent
     * @param string $timezone
     * @param int    $timeshift
     */
    private function handleRecurringExceptions(&$arrFields, $vevent, $timezone, $timeshift): void
    {
        if (
            !\array_key_exists('useExceptions', $arrFields)
            && !\array_key_exists('repeatExceptions', $arrFields)
            && !\array_key_exists('exceptionList', $arrFields)
        ) {
            return;
        }

        $arrFields['useExceptions'] = 0;
        $arrFields['repeatExceptions'] = null;
        $arrFields['exceptionList'] = null;

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

        $arrFields['useExceptions'] = 1;
        ksort($exDates);
        $arrFields['exceptionList'] = $exDates;
        $arrFields['repeatExceptions'] = array_values($exDates);
    }
}