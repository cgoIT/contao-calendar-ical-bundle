<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

/*
 * Content elements
 */
$GLOBALS['TL_CTE']['files']['ical'] = 'ContentICal';

$GLOBALS['BE_MOD']['content']['calendar']['import'] = ['@\Cgoit\ContaoCalendarIcalBundle\Classes\CalendarImport', 'importCalendar'];
$GLOBALS['BE_MOD']['content']['calendar']['stylesheet'] = 'bundles/cgoitcontaocalendarical/calendar-ical.css';

/*
 * Add 'ical' to the URL keywords to prevent problems with URL manipulating modules like folderurl
 */
if (!array_key_exists('urlKeywords', $GLOBALS['TL_CONFIG'])) {
    $GLOBALS['TL_CONFIG'] += ['urlKeywords' => ''];
}
$GLOBALS['TL_CONFIG']['urlKeywords'] .= (strlen(trim((string) $GLOBALS['TL_CONFIG']['urlKeywords'])) ? ',' : '').'ical';

/*
 * Module variables
 */
$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays'] = 365;