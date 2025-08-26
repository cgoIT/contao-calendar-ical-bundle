<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Contao\CalendarModel;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_calendar', target: 'config.ondelete')]
class CalendarDeleteListener
{
    public function __construct(private readonly ExportController $calendarExport)
    {
    }

    /**
     * Update the RSS feed.
     */
    public function __invoke(DataContainer $dc, int $undoId): void
    {
        if (!$dc->id) {
            return;
        }

        $activeRecord = null;
        $contaoVersion = ContaoCoreBundle::getVersion();
        if (version_compare($contaoVersion, '5.0.0') >= 0) {
            $activeRecord = (object) $dc->getCurrentRecord();
        } else {
            $activeRecord = (object) $dc->activeRecord;
        }

        $objCalendar = CalendarModel::findById($activeRecord->id);
        $this->calendarExport->removeSubscriptions($objCalendar);
    }
}
