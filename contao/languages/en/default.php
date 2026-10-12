<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement\ContentIcalElement;
use Cgoit\ContaoCalendarIcalBundle\Controller\Page\IcsFeedController;

$GLOBALS['TL_LANG']['CTE'][ContentIcalElement::TYPE] = ['iCal', 'This element contains a hyperlink to an vCalendar (iCal) which can be downloaded and used to display calendar dates in an external calendar application.'];
$GLOBALS['TL_LANG']['PTY'][IcsFeedController::TYPE] = ['ics Feed', 'This page type generates an ics feed for one or more calendars which can be included in an external calendar application.'];

$GLOBALS['TL_LANG']['MSC']['import_calendar']['0'] = 'Import calendar';
$GLOBALS['TL_LANG']['MSC']['import_calendar']['1'] = 'Import an iCal (.ics) file with events into your calendar.';

$GLOBALS['TL_LANG']['MSC']['export_calendar']['0'] = 'Export calendar';
$GLOBALS['TL_LANG']['MSC']['export_calendar']['1'] = 'Export an iCal (.ics) file with events from your calendar.';

$GLOBALS['TL_LANG']['MSC']['copy_to_clipboard'] = 'Copy the link to the ics file to the clipboard.';

$GLOBALS['TL_LANG']['MSC']['location'] = 'Location';

$GLOBALS['TL_LANG']['tl_maintenance_jobs']['calendar_ical_regen_subscriptions'][0] = 'Recreate the ICS files';
$GLOBALS['TL_LANG']['tl_maintenance_jobs']['calendar_ical_regen_subscriptions'][1] = 'Recreates the ICS files in the <code>shared</code> folder and then purges the shared cache, so there are no links to deleted resources.';

$GLOBALS['TL_LANG']['ERR']['only_one_file'] = 'Please choose only one file for upload!';
$GLOBALS['TL_LANG']['ERR']['ics_parse_error'] = 'The uploaded ics file could not be processed due to a format error.';
$GLOBALS['TL_LANG']['ERR']['ical_protected_calendar'] = 'No iCal subscription can be generated for protected calendars because the file would be publicly available in the "share" directory. Use an "iCal feed" page instead.';
$GLOBALS['TL_LANG']['ERR']['ical_invalid_pattern'] = 'The regular expression for rewriting the title is invalid (it needs delimiters, e.g. /pattern/): %s';
$GLOBALS['TL_LANG']['ERR']['csv_records_skipped'] = '%d records were not imported because the date or time does not match the selected format (record no.: %s).';
$GLOBALS['TL_LANG']['MSC']['ical_import_running'] = 'The iCal source is already being imported. The changed settings are applied by the next import.';
$GLOBALS['TL_LANG']['ERR']['csv_parse_error'] = 'The csv file could not be read.';
$GLOBALS['TL_LANG']['ERR']['csv_no_mapping'] = 'No column was assigned to a field. Please assign at least one column.';
$GLOBALS['TL_LANG']['ERR']['import_failed'] = 'An error occurred during the import. See the system log for details.';
