<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Backend;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\File;
use Contao\Folder;
use Contao\StringUtil;
use Contao\System;

class ExportController extends Backend
{
    public function __construct(
        private readonly IcsExport $icsExport,
        private readonly int $defaultEndTimeDifference,
    ) {
    }

    /**
     * Delete old file and generate feed for calendar(s).
     */
    public function generateSubscriptions(CalendarEventsModel|CalendarModel|null $objCalendar = null): void
    {
        $arrCalendars = [];
        if ($objCalendar instanceof CalendarEventsModel) {
            if (null === $objCalendar = CalendarModel::findById($objCalendar->pid)) {
                return;
            }

            $arrCalendars[] = $objCalendar;
            $this->removeSubscriptions($objCalendar);
        } elseif ($objCalendar instanceof CalendarModel) {
            $arrCalendars[] = $objCalendar;
            $this->removeSubscriptions($objCalendar);
        } else {
            $arrCalendars = CalendarModel::findAll();
            $this->removeSubscriptions();
        }

        foreach ($arrCalendars as $calendar) {
            if (empty($calendar->make_ical)) {
                continue;
            }

            if (false !== $filename = $this->generateFile($calendar)) {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->info('Generated ical subscription "'.$filename.'.ics"')
                ;
            } else {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('Could not generate ical subscription "'.$filename.'.ics"')
                ;
            }
        }
    }

    /**
     * Remove old ics files from the root directory.
     *
     * @return array<string>
     */
    public function removeSubscriptions(CalendarModel|null $objCalendar = null): array
    {
        $arrFeeds = [];
        if (null !== $objCalendar) {
            $arrCalendars = [$objCalendar];

            foreach ($arrCalendars as $objCalendar) {
                $arrFeeds[] = 'calendar'.$objCalendar->id;
                if (!empty($objCalendar->ical_alias)) {
                    $arrFeeds[] = $objCalendar->ical_alias;
                }
            }
        }

        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';

        // Delete old files
        foreach (Folder::scan($shareDir) as $file) {
            if (is_dir($shareDir.$file)) {
                continue;
            }

            $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$file);

            if (
                'ics' === $objFile->extension
                && (empty($arrFeeds) || \in_array($objFile->filename, $arrFeeds, true))
            ) {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->error('delete file '.$objFile->filename)
                ;

                $objFile->delete();
            }
        }

        return [];
    }

    /**
     * Generate an XML file and save it to the root directory.
     */
    private function generateFile(CalendarModel $objCalendar): bool|string
    {
        $startdate = !empty($objCalendar->ical_start) ? (int) $objCalendar->ical_start : time();
        $enddate = !empty($objCalendar->ical_end) ? (int) $objCalendar->ical_end :
            (time() + $this->defaultEndTimeDifference * 24 * 3600);
        $filename = !empty($objCalendar->ical_alias) ? $objCalendar->ical_alias : 'calendar'.$objCalendar->id;
        if (
            null !== $ical = $this->icsExport->getVcalendar([$objCalendar], $startdate, $enddate)
        ) {
            $content = $ical->createCalendar();
            $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';
            $objFile = new File(StringUtil::stripRootDir($shareDir).'/'.$filename.'.ics');
            $objFile->write($content);
            $objFile->close();

            return $filename;
        }

        return false;
    }
}
