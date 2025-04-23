<?php

namespace Cgoit\ContaoCalendarIcalBundle\Event;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Kigkonsult\Icalcreator\Vevent;
use Symfony\Contracts\EventDispatcher\Event;

class AfterImportItemEvent extends Event
{
    public function __construct(
        public readonly CalendarEventsModel $calendarEventsModel,
        public readonly Vevent $vevent,
        public readonly CalendarModel $calendarModel,
    ) {
    }
}
