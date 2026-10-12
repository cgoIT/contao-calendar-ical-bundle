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
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

class ExportController
{
    public function __construct(
        private readonly IcsExport $icsExport,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
        #[Autowire('%contao.web_dir%')]
        private readonly string $webDir,
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
                $this->logger->info('Generated ical subscription "'.$filename.'.ics"');
            } else {
                $this->logger->error('Could not generate ical subscription for calendar "'.$calendar->title.'" ('.$calendar->id.')');
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

        $shareDir = $this->webDir.'/share';
        if (!is_dir($shareDir)) {
            return [];
        }

        $arrRemoved = [];

        foreach (array_diff(scandir($shareDir) ?: [], ['.', '..']) as $file) {
            if (is_dir($shareDir.'/'.$file)) {
                continue;
            }

            if ('ics' !== pathinfo($file, PATHINFO_EXTENSION)) {
                continue;
            }

            $name = pathinfo($file, PATHINFO_FILENAME);
            $isOrphan = null === $objCalendar && 1 === preg_match('/^calendar\d+$/', $name);

            if ($isOrphan || \in_array($name, $arrFeeds, true)) {
                $this->logger->info('delete file '.$name);

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
        [$startdate, $enddate] = $this->icsExport->getTimeRange($objCalendar);
        $filename = $this->getSubscriptionFileName($objCalendar);
        if (
            null !== $ical = $this->icsExport->getVcalendar([$objCalendar], $startdate, $enddate)
        ) {
            $content = $ical->createCalendar();
            $shareDir = $this->webDir.'/share';
            (new Filesystem())->dumpFile($shareDir.'/'.$filename.'.ics', $content);

            return $filename;
        }

        return false;
    }
}
