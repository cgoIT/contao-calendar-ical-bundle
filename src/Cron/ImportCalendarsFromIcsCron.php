<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Cron;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCronJob('hourly')]
class ImportCalendarsFromIcsCron
{
    public function __construct(
        private readonly IcsImport $icsImport,
        private readonly ExportController $calendarExport,
        private readonly ContaoFramework $contaoFramework,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $this->contaoFramework->initialize();

        $arrCalendars = CalendarModel::findBy(['ical_source != ?'], ['']);

        if (empty($arrCalendars)) {
            return;
        }

        foreach ($arrCalendars as $arrCalendar) {
            // Only calendars whose cache time ("ical_cache") is over are imported
            try {
                // The subscription file of a calendar with a web source depends on the imported events, too
                if ($this->icsImport->importIcsForCalendar($arrCalendar) && !empty($arrCalendar->make_ical)) {
                    $this->calendarExport->generateSubscriptions($arrCalendar);
                }
            } catch (\Throwable $e) {
                // A broken source must not prevent the import of the other calendars
                $this->logger->error('Could not import the web calendar "'.$arrCalendar->title.'" ('.$arrCalendar->id.'): '.$e->getMessage());
            }
        }
    }
}
