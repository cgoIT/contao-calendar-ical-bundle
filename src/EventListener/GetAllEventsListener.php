<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener;

use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;

#[AsHook('getAllEvents')]
class GetAllEventsListener
{
    public function __construct(private readonly IcsImport $icsImport)
    {
    }

    /**
     * @param array<mixed> $events
     * @param array<mixed> $calendars
     */
    public function __invoke(array $events, array $calendars, int $timeStart, int $timeEnd, Module $module): array
    {
        $arrCalendars = CalendarModel::findBy(
            ['id IN ('.implode(',', $calendars).')', 'ical_source=?'],
            ['1'],
        );

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $calendar) {
                $this->icsImport->importIcsForCalendar($calendar);
            }
        }

        return $events;
    }
}
