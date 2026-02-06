<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
class CalendarEventsSubmitListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ExportController $calendarExport,
    ) {
    }

    /**
     * Update the RSS feed.
     */
    public function __invoke(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $act = $request->get('act');
        $field = $request->get('field');
        if (!$dc->id || 'toggle' !== $act || 'published' !== $field) {
            return;
        }

        $calendarEvent = CalendarEventsModel::findById($dc->id);

        if (null !== $calendarEvent) {
            $calendarEvent->refresh();
            $this->calendarExport->generateSubscriptions($calendarEvent);
        }
    }
}
