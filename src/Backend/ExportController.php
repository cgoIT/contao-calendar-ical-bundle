<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
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
use Symfony\Component\Filesystem\Filesystem;

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
            // Subscription files are public, so a protected calendar must never be written to disk
            if (empty($calendar->make_ical) || $calendar->protected) {
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
                    ->error('Could not generate ical subscription for calendar "'.$calendar->title.'" ('.$calendar->id.')')
                ;
            }
        }
    }

    /**
     * The name (without extension) of the subscription file of a calendar.
     */
    public function getSubscriptionFileName(CalendarModel $objCalendar): string
    {
        return !empty($objCalendar->ical_alias) ? (string) $objCalendar->ical_alias : 'calendar'.$objCalendar->id;
    }

    /**
     * Remove old subscription files from the "share" directory. Without a calendar the files of all
     * calendars (and orphaned "calendar<ID>.ics" files) are removed; files of other origin stay untouched.
     *
     * @return array<string> The names of the removed files
     */
    public function removeSubscriptions(CalendarModel|null $objCalendar = null): array
    {
        $arrFeeds = [];
        $arrCalendars = null !== $objCalendar ? [$objCalendar] : $this->findAllCalendars();

        foreach ($arrCalendars as $calendar) {
            $arrFeeds[] = 'calendar'.$calendar->id;
            $arrFeeds[] = $this->getSubscriptionFileName($calendar);
        }

        $shareDir = System::getContainer()->getParameter('contao.web_dir').'/share';
        if (!is_dir($shareDir)) {
            return [];
        }

        $arrRemoved = [];

        foreach (Folder::scan($shareDir) as $file) {
            if (is_dir($shareDir.'/'.$file)) {
                continue;
            }

            if ('ics' !== pathinfo($file, PATHINFO_EXTENSION)) {
                continue;
            }

            $name = pathinfo($file, PATHINFO_FILENAME);
            $isOrphan = null === $objCalendar && 1 === preg_match('/^calendar\d+$/', $name);

            if ($isOrphan || \in_array($name, $arrFeeds, true)) {
                System::getContainer()
                    ->get('monolog.logger.contao.general')
                    ->info('delete file '.$name)
                ;

                // The files are not part of the file manager, so the database assisted file system is not involved
                (new Filesystem())->remove($shareDir.'/'.$file);
                $arrRemoved[] = $file;
            }
        }

        return $arrRemoved;
    }

    /**
     * @return iterable<CalendarModel>
     */
    protected function findAllCalendars(): iterable
    {
        return CalendarModel::findAll() ?? [];
    }

    /**
     * Generate an XML file and save it to the root directory.
     */
    private function generateFile(CalendarModel $objCalendar): bool|string
    {
        $startdate = !empty($objCalendar->ical_start) ? (int) $objCalendar->ical_start : time();
        $enddate = !empty($objCalendar->ical_end) ? (int) $objCalendar->ical_end :
            (time() + $this->defaultEndTimeDifference * 24 * 3600);
        $filename = $this->getSubscriptionFileName($objCalendar);
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
