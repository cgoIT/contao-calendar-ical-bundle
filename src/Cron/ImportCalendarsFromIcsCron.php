<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Cron;

use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;

#[AsCronJob('daily')]
class ImportCalendarsFromIcsCron
{
    public function __construct(
        private readonly IcsImport $icsImport,
        private readonly ContaoFramework $contaoFramework,
    ) {
    }

    public function __invoke(): void
    {
        $this->contaoFramework->initialize();

        $arrCalendars = CalendarModel::findBy(['ical_source != ?'], ['']);

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $arrCalendar) {
                $this->icsImport->importIcsForCalendar($arrCalendar, true);
            }
        }
    }
}
