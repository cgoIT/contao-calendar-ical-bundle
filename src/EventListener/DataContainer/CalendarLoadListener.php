<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Classes\CalendarExport;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_calendar', target: 'config.onload')]
class CalendarLoadListener
{
    public function __construct(private readonly CalendarExport $calendarExport)
    {
    }

    /**
     * Update the RSS feed.
     */
    public function __invoke(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $this->calendarExport->exportCalendar($dc->id);
    }
}