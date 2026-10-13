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
use Cgoit\ContaoCalendarIcalBundle\Util\UploadedFileResolver;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Controller;
use Contao\CoreBundle\Slug\Slug;
use Contao\Date;
use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class CsvImport extends AbstractImport
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $projectDir,
        Slug $slug,
        private readonly UploadedFileResolver $uploadedFileResolver,
    ) {
        parent::__construct($slug);
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
     *
     * @throws \InvalidArgumentException If the path is not the one of an uploaded csv file
     */
    public function readPreview(string $file, string $encoding, int $lines = 5): array
    {
        $parser = new CsvParser($this->resolvePath($file), '' !== $encoding ? $encoding : 'utf8');
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
     *
     * @throws \InvalidArgumentException If the file is not an uploaded csv file or has no header
     */
    public function import(string $file, int $calendarId, array $mapping, string $encoding, string $dateFormat, string $timeFormat, Date $startDate, Date|null $endDate, bool $deleteCalendar, int $timeshift, string $filterEventTitle): array
    {
        $parser = new CsvParser($this->resolvePath($file), '' !== $encoding ? $encoding : 'utf8');

        // Check the file before anything is deleted: without a header nothing could be imported
        if ([] === $parser->extractHeader()) {
            throw new \InvalidArgumentException('The csv file is empty or cannot be read');
        }

        Controller::loadDataContainer('tl_calendar_events');

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
            $publish = $this->publishImportedEvents(CalendarModel::findById($calendarId));

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

                    foreach ($defaultFields as $field => $value) {
                        $varValue = $value['default'];
                        if ($varValue instanceof \Closure) {
                            $varValue = $varValue();
                        }
                        $objEvent->{$field} = $varValue;
                    }

                    $objEvent->published = $publish;
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
     * The absolute path of an uploaded csv file.
     *
     * @throws \InvalidArgumentException If the path is not the one of an uploaded csv file
     */
    private function resolvePath(string $file): string
    {
        $resolved = $this->uploadedFileResolver->resolve($file, ['csv']);

        if (null === $resolved) {
            throw new \InvalidArgumentException('Invalid csv file path');
        }

        return $this->projectDir.'/'.$resolved;
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
