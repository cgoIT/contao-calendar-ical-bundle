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

use Cgoit\ContaoCalendarIcalBundle\Util\UploadedFileResolver;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Date;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Imports an uploaded ics file into a calendar in several steps: the file is read and checked first, the
 * time zone is clarified (if necessary) and then the events are imported. Contains no user interface.
 */
class IcsFileImporter
{
    public function __construct(
        private readonly IcsImport $icsImport,
        private readonly UploadedFileResolver $uploadedFileResolver,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Reads and parses an uploaded ics file.
     *
     * @param string $path The path of the file in the upload directory, relative to the project
     *
     * @throws \InvalidArgumentException If the path is not allowed or the file is empty or no valid ics file
     */
    public function load(string $path, CalendarModel $calendar): Vcalendar
    {
        $resolved = $this->uploadedFileResolver->resolve($path);

        if (null === $resolved) {
            throw new \InvalidArgumentException('Invalid ics file path');
        }

        $content = (string) file_get_contents($this->projectDir.'/'.$resolved);

        if ('' === $content) {
            throw new \InvalidArgumentException('Ical content empty');
        }

        $cal = new Vcalendar();
        $cal->setMethod(IcalInterface::PUBLISH);
        $cal->setXprop(IcalInterface::X_WR_CALNAME, $calendar->title);
        $cal->setXprop(IcalInterface::X_WR_CALDESC, $calendar->title);

        try {
            $cal->parse($content);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('The ics file could not be parsed: '.$e->getMessage(), 0, $e);
        }

        return $cal;
    }

    /**
     * The time zone the calendar tells for its events.
     *
     * @return array{0: string, 1: string}|null The name of the property and the time zone (as the importer expects it)
     */
    public function getTimezone(Vcalendar $cal): array|null
    {
        $tz = $cal->getXprop(IcalInterface::X_WR_TIMEZONE);
        $component = $cal->getComponent(IcalInterface::VTIMEZONE);

        if (false === $tz && !empty($component)) {
            $tz = $component->getXprop(IcalInterface::X_LIC_LOCATION);
        }

        if (!\is_array($tz) || empty($tz[1])) {
            return null;
        }

        return [(string) $tz[0], (string) $tz[1]];
    }

    public function checkTimezone(Vcalendar $cal): TimezoneCheck
    {
        $tz = $this->getTimezone($cal);

        if (null === $tz) {
            return TimezoneCheck::Missing;
        }

        return $tz[1] === (string) Config::get('timeZone') ? TimezoneCheck::Matches : TimezoneCheck::Differs;
    }

    /**
     * Imports the events of the calendar and deletes the uploaded file afterwards.
     *
     * @param string|null $timezone          The time zone to use if the file has none (or is to be overruled)
     * @param bool        $useSystemTimezone Replaces the time zone of the file by the time zone of the system
     */
    public function import(string $path, Vcalendar $cal, CalendarModel $calendar, Date $startDate, Date $endDate, string|null $timezone, bool $useSystemTimezone, bool $deleteCalendar, int $timeshift, string|null $filterEventTitle): void
    {
        $tz = $this->getTimezone($cal);

        if ($useSystemTimezone) {
            $timezone = (string) Config::get('timeZone');
        }

        if (!empty($timezone)) {
            $tz = null !== $tz ? [$tz[0], $timezone] : [$timezone, $timezone];
        }

        try {
            $this->icsImport->importFromIcsFile($cal, $calendar, $startDate, $endDate, $tz ?? false, $filterEventTitle, null, null, $deleteCalendar, $timeshift);
        } finally {
            $this->delete($path);
        }
    }

    /**
     * Deletes an uploaded file, which is not needed anymore.
     */
    public function delete(string $path): void
    {
        $resolved = $this->uploadedFileResolver->resolve($path);

        if (null !== $resolved) {
            (new Filesystem())->remove($this->projectDir.'/'.$resolved);
        }
    }
}
