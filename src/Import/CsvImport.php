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
use Cgoit\ContaoCalendarIcalBundle\Util\UploadedIcsFileResolver;
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
use Symfony\Component\Filesystem\Filesystem;
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
        private readonly UploadedIcsFileResolver $uploadedFileResolver,
    ) {
        parent::__construct($slug);

        $this->timezoneUtil = $timezoneUtil;
    }

    public function importFromCSVFile(bool $prepare = true): string
    {
        Controller::loadDataContainer('tl_calendar_events');

        $calfields = [];

        foreach ($this->getCalendarFields() as $name => $label) {
            $calfields[] = [$name, $label];
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

        $mapping = [];

        foreach ((array) $calvalues as $idx => $value) {
            $foundindex = array_search($csvvalues[$idx] ?? null, $header, true);

            if (false !== $foundindex) {
                $mapping[] = [(int) $foundindex, (string) $value];
            }
        }

        $skippedRecords = $this->import(
            (string) $importSettings['csv_filename'],
            (int) ($importSettings['csv_pid'] ?? 0),
            $mapping,
            (string) $encoding,
            (string) $dateFormat,
            (string) $timeFormat,
            new Date($importSettings['csv_startdate'], $GLOBALS['TL_CONFIG']['dateFormat']),
            !empty((string) ($importSettings['csv_enddate'] ?? '')) ? new Date($importSettings['csv_enddate'], $GLOBALS['TL_CONFIG']['dateFormat']) : null,
            !empty($importSettings['csv_deletecalendar']) && !empty($importSettings['csv_pid']),
            (int) $importSettings['csv_timeshift'],
            (string) ($importSettings['csv_filterEventTitle'] ?? ''),
        );

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
     * The fields of an event a column of a csv file can be assigned to.
     *
     * @return array<string, string> The names of the fields with their labels
     */
    public function getCalendarFields(): array
    {
        Controller::loadDataContainer('tl_calendar_events');

        $columns = array_map(
            static fn ($column): string => $column->getName(),
            $this->db->createSchemaManager()->listTableColumns('tl_calendar_events'),
        );

        $fields = [];

        foreach (['title', 'startTime', 'endTime', 'startDate', 'endDate', 'details', 'teaser', 'location', 'cep_participants', 'location_contact'] as $name) {
            // The last three fields exist only if the corresponding bundles are installed
            if (\in_array($name, ['location', 'cep_participants', 'location_contact'], true) && !\in_array($name, $columns, true)) {
                continue;
            }

            $fields[$name] = (string) $GLOBALS['TL_LANG']['tl_calendar_events'][$name][0];
        }

        return $fields;
    }

    /**
     * Reads the header and the first records of a csv file.
     *
     * @param string $file The path of the csv file relative to the project directory
     *
     * @return array{header: list<string>, rows: list<list<string>>}
     */
    public function readPreview(string $file, string $encoding, int $lines = 5): array
    {
        $parser = new CsvParser($this->projectDir.'/'.$file, '' !== $encoding ? $encoding : 'utf8');
        $header = array_map(static fn ($cell): string => (string) $cell, $parser->extractHeader());
        $rows = [];

        $data = $parser->getDataArray($lines);

        foreach (\is_array($data) ? $data : [] as $row) {
            if (\is_array($row)) {
                $rows[] = array_map(static fn ($cell): string => (string) $cell, array_values($row));
            }
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Deletes an uploaded csv file, which is not needed anymore.
     */
    public function delete(string $file): void
    {
        $resolved = $this->uploadedFileResolver->resolve($file, ['csv']);

        if (null !== $resolved) {
            (new Filesystem())->remove($this->projectDir.'/'.$resolved);
        }
    }

    /**
     * Imports the events of a csv file.
     *
     * @param string                         $file      The path of the csv file relative to the project directory
     * @param list<array{0: int, 1: string}> $mapping   The index of the column in the file and the field of the event, for every imported column
     * @param Date                           $startDate The first day to import
     * @param Date|null                      $endDate   The last day to import, null for no end
     *
     * @return list<int> The numbers of the records that were skipped because of a wrong date or time
     */
    public function import(string $file, int $calendarId, array $mapping, string $encoding, string $dateFormat, string $timeFormat, Date $startDate, Date|null $endDate, bool $deleteCalendar, int $timeshift, string $filterEventTitle): array
    {
        Controller::loadDataContainer('tl_calendar_events');

        $parser = new CsvParser($this->projectDir.'/'.$file, '' !== $encoding ? $encoding : 'utf8');
        $parser->extractHeader();

        $skippedRecords = [];

        // Either everything or nothing is imported (also if the calendar is emptied before)
        $this->db->beginTransaction();

        try {
            if ($deleteCalendar) {
                $objEvents = CalendarEventsModel::findByPid($calendarId);
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
                    $objEvent->pid = $calendarId;
                    $objEvent->published = true;

                    foreach ($defaultFields as $field => $value) {
                        $varValue = $value['default'];
                        if ($varValue instanceof \Closure) {
                            $varValue = $varValue();
                        }
                        $objEvent->{$field} = $varValue;
                    }

                    $objEvent->author = BackendUser::getInstance()->id ?? 0;

                    foreach ($mapping as [$foundindex, $value]) {
                        if (!empty($value) && !empty($data[$foundindex])) {
                            switch ($value) {
                                case 'startDate':
                                case 'endDate':
                                    $timestamp = $this->parseDate($dateFormat, (string) $data[$foundindex]);

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
                                    if ('' !== $filterEventTitle && !str_contains(mb_strtolower($this->encodeImportedText((string) $data[$foundindex])), mb_strtolower($filterEventTitle))) {
                                        continue 3;
                                    }
                                // no break
                                default:
                                    $objEvent->{$value} = $this->encodeImportedText((string) $data[$foundindex]);
                                    break;
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

                    foreach ($mapping as [$foundindex, $value]) {
                        switch ($value) {
                            case 'startTime':
                            case 'endTime':
                                if (empty($data[$foundindex])) {
                                    break;
                                }

                                $seconds = $this->parseTime($timeFormat, (string) $data[$foundindex]);

                                if (null === $seconds) {
                                    $skippedRecords[] = $record;
                                    continue 3;
                                }

                                $objEvent->{$value} = ('startTime' === $value ? $objEvent->startDate : $objEvent->endDate) + $seconds;
                                break;
                        }
                    }

                    if ((!empty($objEvent->startTime) && $objEvent->startDate !== $objEvent->startTime) || (!empty($objEvent->endTime) && $objEvent->endDate !== $objEvent->endTime)) {
                        $objEvent->addTime = true;
                    }

                    if (empty($objEvent->title)) {
                        $objEvent->title = $GLOBALS['TL_LANG']['tl_calendar_events']['untitled'];
                    }

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

                    if (empty($objEvent->source)) {
                        $objEvent->source = 'default';
                    }

                    if ($objEvent->endDate < $startDate->tstamp || (null !== $endDate && ($objEvent->startDate > $endDate->tstamp))) {
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

        return $skippedRecords;
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
