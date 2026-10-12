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
use Kigkonsult\Icalcreator\Vevent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched for every exported Vevent. The Vevent can be modified or replaced.
 */
class ModifyVeventEvent extends Event
{
    public function __construct(
        public Vevent $vevent,
        public readonly CalendarEventsModel $calendarEventModel,
        public readonly CalendarModel|null $calendarModel,
    ) {
    }
}
