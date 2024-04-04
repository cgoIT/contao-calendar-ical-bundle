<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_calendar', target: 'config.onsubmit')]
class CalendarSubmitListener
{
    public function __construct(
        private readonly IcsImport $icsImport,
        private readonly ExportController $calendarExport,
    ) {
    }

    public function __invoke(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $objCalendar = CalendarModel::findById($dc->id);
        if (!empty($objCalendar)) {
            $objCalendar->refresh();

            $this->icsImport->importIcsForCalendar($objCalendar);
            $this->calendarExport->generateSubscriptions($objCalendar);
        }
    }
}
