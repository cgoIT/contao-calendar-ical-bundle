<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_calendar', target: 'fields.ical_alias.save')]
class CalendarIcalAliasSaveListener
{
    public function __construct(private readonly ExportController $calendarExport)
    {
    }

    /**
     * Update the RSS feed.
     */
    public function __invoke(mixed $value, DataContainer $dc): mixed
    {
        if (!$dc->id) {
            return $value;
        }

        $objCalendar = CalendarModel::findById($dc->id);

        if (null !== $objCalendar && $value !== $objCalendar->ical_alias) {
            $this->calendarExport->removeSubscriptions($objCalendar);
        }

        return $value;
    }
}
