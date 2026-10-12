<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Event;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before the events of a calendar are exported. The list of events can be replaced.
 */
class ModifyExportEventsEvent extends Event
{
    /**
     * @param array<CalendarEventsModel> $events    The events of the calendar within the time range
     * @param array<CalendarModel>       $calendars All calendars of the export
     */
    public function __construct(
        public array $events,
        public readonly CalendarModel $calendarModel,
        public readonly array $calendars,
        public readonly int $start,
        public readonly int $end,
    ) {
    }
}
