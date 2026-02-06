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

use Contao\BackendUser;
use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_content', target: 'fields.ical_calendar.options')]
#[AsCallback(table: 'tl_page', target: 'fields.ical_calendar.options')]
class CalendarOptionsListener
{
    /**
     * @return array<mixed>
     */
    public function __invoke(DataContainer $dc): array
    {
        $user = BackendUser::getInstance();

        if (!$user->isAdmin && !\is_array($user->calendars)) {
            return [];
        }

        $arrOptions = [];
        $arrCalendars = CalendarModel::findAll(['order' => 'tl_calendar.title']);

        if (!empty($arrCalendars)) {
            foreach ($arrCalendars as $objCalendar) {
                if ($user->isAdmin || \in_array($objCalendar->id, $user->calendars, true)) {
                    $arrOptions[$objCalendar->id] = $objCalendar->title;
                }
            }
        }

        return $arrOptions;
    }
}
