<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Event;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Kigkonsult\Icalcreator\Vevent;
use Symfony\Contracts\EventDispatcher\Event;

class BeforeImportItemEvent extends Event
{
    public bool $skip = false;
    public function __construct(
        public readonly CalendarEventsModel $calendarEventModel,
        public readonly Vevent $vevent,
        public readonly CalendarModel $calendarModel,
    ) {
    }
}
