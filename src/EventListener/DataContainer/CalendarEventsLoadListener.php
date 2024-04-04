<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_calendar_events', target: 'config.onload')]
class CalendarEventsLoadListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ExportController $calendarExport,
    ) {
    }

    public function __invoke(DataContainer|null $dc): void
    {
        $act = $this->requestStack->getCurrentRequest()->get('act');
        if (null === $dc || !$dc->id || 'toggle' === $act || 'delete' === $act) {
            return;
        }

        $objModel = match ($act) {
            null => CalendarModel::findById($dc->id),
            default => CalendarEventsModel::findById($dc->id),
        };

        if (null !== $objModel && !empty($objModel->tstamp)) {
            $objModel->refresh();
            $this->calendarExport->generateSubscriptions($objModel);
        }
    }
}
