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

use Cgoit\ContaoCalendarIcalBundle\Import\Csv\CsvParser;
use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtil;
use Cgoit\ContaoCalendarIcalBundle\Widgets\WidgetTrait;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Contao\Environment;
use Contao\File;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsvImport extends AbstractImport
{
    use WidgetTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Connection $db,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $csrfTokenName,
        private readonly string $projectDir,
        Slug $slug,
        TimezoneUtil $timezoneUtil,
    ) {
        parent::__construct($slug);

        $this->timezoneUtil = $timezoneUtil;
    }

    public function importFromCSVFile(bool $prepare = true): string
    {
        Controller::loadDataContainer('tl_calendar_events');

        $fieldnames = [];

        $schemaManager = $this->db->createSchemaManager();
        $dbfields = $schemaManager->listTableColumns('tl_calendar_events');

        foreach ($dbfields as $dbfield) {
            $fieldnames[] = $dbfield->getName();
        }

        $calfields =
            [
                ['title', $GLOBALS['TL_LANG']['tl_calendar_events']['title'][0]],
                ['startTime', $GLOBALS['TL_LANG']['tl_calendar_events']['startTime'][0]],
                ['endTime', $GLOBALS['TL_LANG']['tl_calendar_events']['endTime'][0]],
                ['startDate', $GLOBALS['TL_LANG']['tl_calendar_events']['startDate'][0]],
                ['endDate', $GLOBALS['TL_LANG']['tl_calendar_events']['endDate'][0]],
                ['details', $GLOBALS['TL_LANG']['tl_calendar_events']['details'][0]],
                ['teaser', $GLOBALS['TL_LANG']['tl_calendar_events']['teaser'][0]],
            ];

        if (\in_array('location', $fieldnames, true)) {
            $calfields[] = ['location', $GLOBALS['TL_LANG']['tl_calendar_events']['location'][0]];
        }
        if (\in_array('cep_participants', $fieldnames, true)) {
            $calfields[] = ['cep_participants', $GLOBALS['TL_LANG']['tl_calendar_events']['cep_participants'][0]];
        }
        if (\in_array('location_contact', $fieldnames, true)) {
            $calfields[] = ['location_contact', $GLOBALS['TL_LANG']['tl_calendar_events']['location_contact'][0]];
        }

        $dateFormat = Input::post('dateFormat');
        $timeFormat = Input::post('timeFormat');
        $fields = [];
        $csvvalues = Input::post('csvfield');
        $calvalues = Input::post('calfield');
        $encoding = Input::post('encoding');

        $importSettings = StringUtil::deserialize(
            $this->requestStack
                ->getCurrentRequest()
                ->getSession()
                ->get('calendar_ical.csvimport.settings'),
            true,
        );

        $data = $this->projectDir.'/'.$importSettings['csv_filename'];
        $parser = new CsvParser($data, !empty((string) $encoding) ? $encoding : 'utf8');
        $header = $parser->extractHeader();

        for ($i = 0; $i < (!empty($header) ? \count($header) : 0); ++$i) {
            $objCSV = $this->getFieldSelector($i, 'csvfield', $header,
                \is_array($csvvalues) ? $csvvalues[$i] : $header[$i]);
            $objCal = $this->getFieldSelector($i, 'calfield', $calfields, $calvalues[$i] ?? null);
            $fields[] = [$objCSV, $objCal];
        }

        if ($prepare) {
            $preview = $parser->getDataArray(5);
            $objTemplate = new BackendTemplate('be_import_calendar_csv_headers');
            $objTemplate->request_token = $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue();

            $objTemplate->lngFields = $GLOBALS['TL_LANG']['tl_calendar_events']['ical_bundle_fields'];
            $objTemplate->lngPreview = $GLOBALS['TL_LANG']['tl_calendar_events']['preview'];
            $objTemplate->check = $GLOBALS['TL_LANG']['tl_calendar_events']['check'];
            $objTemplate->header = array_map(static fn ($cell): string => StringUtil::specialchars((string) $cell), $header);

            if (!empty($preview)) {
                foreach ($preview as $idx => $line) {
                    if (\is_array($line)) {
                        foreach ($line as $key => $value) {
                            $preview[$idx][$key] = StringUtil::specialchars((string) $value);
                        }
                    }
                }
            }

            $objTemplate->preview = $preview;
            $objTemplate->encoding = $this->getEncodingWidget($encoding);

            if (\function_exists('date_parse_from_format')) {
                $objTemplate->dateFormat = $this->getDateFormatWidget($dateFormat);
                $objTemplate->timeFormat = $this->getTimeFormatWidget($timeFormat);
            }

            $objTemplate->hrefBack = StringUtil::ampersand(str_replace('&key=import', '', (string) Environment::get('request')));
            $objTemplate->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
            $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['import_calendar'][0];
            $objTemplate->request = StringUtil::ampersand(Environment::get('request'), true);
            $objTemplate->submit = StringUtil::specialchars($GLOBALS['TL_LANG']['tl_calendar_events']['proceed'][0]);
            $objTemplate->fields = $fields;

            return $objTemplate->parse();
        }

        $skippedRecords = [];

        // Either everything or nothing is imported (also if the calendar is emptied before)
        $this->db->beginTransaction();

        try {
            if (!empty($importSettings['csv_deletecalendar']) && !empty($importSettings['csv_pid'])) {
                $objEvents = CalendarEventsModel::findByPid($importSettings['csv_pid']);
                $this->deleteEvents($objEvents);
            }

            $done = false;
            $record = 1; // the header

            // Get all default values for new entries
            $defaultFields = array_filter($GLOBALS['TL_DCA']['tl_calendar_events']['fields'], static fn ($val) => isset($val['default']));

            while (!$done) {
                $data = $parser->getDataArray();

                if (false !== $data) {
                    ++$record;
                    $eventcontent = [];

                    $objEvent = new CalendarEventsModel();
                    $objEvent->tstamp = time();
                    $objEvent->pid = $importSettings['csv_pid'];
                    $objEvent->published = true;

                    foreach ($defaultFields as $field => $value) {
                        $varValue = $value['default'];
                        if ($varValue instanceof \Closure) {
                            $varValue = $varValue();
                        }
                        $objEvent->{$field} = $varValue;
                    }

                    $objEvent->author = BackendUser::getInstance()->id ?? 0;

                    foreach ($calvalues as $idx => $value) {
                        if (!empty($value)) {
                            $indexfield = $csvvalues[$idx];
                            $foundindex = array_search($indexfield, $header, true);

                            if (false !== $foundindex && !empty($data[$foundindex])) {
                                switch ($value) {
                                    case 'startDate':
                                    case 'endDate':
                                        $timestamp = $this->parseDate((string) Input::post('dateFormat'), (string) $data[$foundindex]);

                                        // A wrong date must not silently become another one
                                        if (null === $timestamp) {
                                            $skippedRecords[] = $record;
                                            continue 3;
                                        }

                                        $objEvent->{$value} = $timestamp;
                                        break;
                                    case 'details':
                                        $eventcontent[] = $this->encodeImportedText((string) $data[$foundindex]);
                                        break;
                                    case 'title':
                                        $objEvent->{$value} = $this->encodeImportedText((string) $data[$foundindex]);
                                        $filterEventTitle = $importSettings['csv_filterEventTitle'];
                                        if (!empty($filterEventTitle) && !str_contains(mb_strtolower($this->encodeImportedText((string) $data[$foundindex])), mb_strtolower((string) $filterEventTitle))) {
                                            continue 3;
                                        }
                                    // no break
                                    default:
                                        $objEvent->{$value} = $this->encodeImportedText((string) $data[$foundindex]);
                                        break;
                                }
                            }
                        }
                    }

                    if (empty($objEvent->startDate)) {
                        $today = getdate();
                        $objEvent->startDate = mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']);
                        $objEvent->startTime = $objEvent->startDate;
                    }

                    if (empty($objEvent->endDate)) {
                        $objEvent->endDate = $objEvent->startDate;
                    }

                    foreach ($calvalues as $idx => $value) {
                        if (!empty($value)) {
                            $indexfield = $csvvalues[$idx];
                            $foundindex = array_search($indexfield, $header, true);

                            if (false !== $foundindex) {
                                switch ($value) {
                                    case 'startTime':
                                    case 'endTime':
                                        if (empty($data[$foundindex])) {
                                            break;
                                        }

                                        $seconds = $this->parseTime((string) Input::post('timeFormat'), (string) $data[$foundindex]);

                                        if (null === $seconds) {
                                            $skippedRecords[] = $record;
                                            continue 3;
                                        }

                                        $objEvent->{$value} = ('startTime' === $value ? $objEvent->startDate : $objEvent->endDate) + $seconds;
                                        break;
                                }
                            }
                        }
                    }

                    if ((!empty($objEvent->startTime) && $objEvent->startDate !== $objEvent->startTime) || (!empty($objEvent->endTime) && $objEvent->endDate !== $objEvent->endTime)) {
                        $objEvent->addTime = true;
                    }

                    if (empty($objEvent->title)) {
                        $objEvent->title = $GLOBALS['TL_LANG']['tl_calendar_events']['untitled'];
                    }

                    $timeshift = (int) $importSettings['csv_timeshift'];

                    if (0 !== $timeshift) {
                        $objEvent->startDate += $timeshift * 3600;
                        $objEvent->endDate += $timeshift * 3600;
                        if (!empty($objEvent->startTime)) {
                            $objEvent->startTime += $timeshift * 3600;
                        }
                        if (!empty($objEvent->endTime)) {
                            $objEvent->endTime += $timeshift * 3600;
                        }
                    }

                    $startDate = new Date($importSettings['csv_startdate'], $GLOBALS['TL_CONFIG']['dateFormat']);
                    $endDate = new Date($importSettings['csv_enddate'], $GLOBALS['TL_CONFIG']['dateFormat']);

                    if (empty($objEvent->source)) {
                        $objEvent->source = 'default';
                    }

                    if ($objEvent->endDate < $startDate->tstamp || (!empty((string) $importSettings['csv_enddate']) && ($objEvent->startDate > $endDate->tstamp))) {
                        // date is not in range
                    } else {
                        $objEvent = $objEvent->save();
                        if (!empty($eventcontent)) {
                            $this->addEventContent($objEvent, $eventcontent);
                        }

                        $this->generateAlias($objEvent);
                    }
                } else {
                    $done = true;
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();

            throw $e;
        }

        $this->requestStack
            ->getCurrentRequest()
            ->getSession()
            ->remove('calendar_ical.csvimport.settings')
        ;

        // The uploaded file is not needed anymore
        if (!empty($importSettings['csv_filename'])) {
            (new File($importSettings['csv_filename']))->delete();
        }

        if (!empty($skippedRecords)) {
            Message::addError(\sprintf($GLOBALS['TL_LANG']['ERR']['csv_records_skipped'], \count($skippedRecords), implode(', ', \array_slice($skippedRecords, 0, 20)).(\count($skippedRecords) > 20 ? ', ...' : '')));
        }

        Controller::redirect(str_replace('&key=import', '', (string) Environment::get('request')));
    }

    /**
     * Parses a date with the given format; returns the timestamp of midnight or null if the value does not match.
     */
    private function parseDate(string $format, string $value): int|null
    {
        $res = date_parse_from_format($format, trim($value));

        if ($res['error_count'] > 0 || $res['warning_count'] > 0 || false === $res['year'] || false === $res['month'] || false === $res['day']) {
            return null;
        }

        return mktime(0, 0, 0, $res['month'], $res['day'], $res['year']);
    }

    /**
     * Parses a time with the given format; returns the seconds since midnight or null if the value does not match.
     */
    private function parseTime(string $format, string $value): int|null
    {
        $res = date_parse_from_format($format, trim($value));

        if ($res['error_count'] > 0 || $res['warning_count'] > 0 || false === $res['hour'] || false === $res['minute']) {
            return null;
        }

        return $res['hour'] * 3600 + $res['minute'] * 60 + $res['second'];
    }
}
