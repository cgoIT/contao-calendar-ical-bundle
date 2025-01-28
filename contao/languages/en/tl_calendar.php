<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

$GLOBALS['TL_LANG']['tl_calendar']['ical_legend'] = 'iCal settings';

$GLOBALS['TL_LANG']['tl_calendar']['ical_alias']['0'] = 'iCal alias';
$GLOBALS['TL_LANG']['tl_calendar']['ical_alias']['1'] = 'Here you can enter a unique filename (without extension). The iCal subscription file will be auto-generated in the root directory of your Contao installation, e.g. as <em>name.ics</em>.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_prefix']['0'] = 'Title prefix';
$GLOBALS['TL_LANG']['tl_calendar']['ical_prefix']['1'] = 'Here you can enter a prefix that will be added to every event title in the iCal subscription.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_description']['0'] = 'iCal description';
$GLOBALS['TL_LANG']['tl_calendar']['ical_description']['1'] = 'Please enter a short description of the calendar.';

$GLOBALS['TL_LANG']['tl_calendar']['make_ical']['0'] = 'Generate iCal subscription';
$GLOBALS['TL_LANG']['tl_calendar']['make_ical']['1'] = 'Generate an iCal subscription file from the calendar.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_source']['0'] = 'iCal web source';
$GLOBALS['TL_LANG']['tl_calendar']['ical_source']['1'] = 'Create a calendar from an iCal web source.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_url']['0'] = 'iCal URL';
$GLOBALS['TL_LANG']['tl_calendar']['ical_url']['1'] = 'Please enter the URL to the iCal .ics file.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_proxy']['0'] = 'Proxy';
$GLOBALS['TL_LANG']['tl_calendar']['ical_proxy']['1'] = 'Please specify the CURL proxy if you use Contao behind a proxy.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_bnpw']['0'] = 'Username:Password';
$GLOBALS['TL_LANG']['tl_calendar']['ical_bnpw']['1'] = 'Please enter username:password for CURL proxy.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_port']['0'] = 'Port';
$GLOBALS['TL_LANG']['tl_calendar']['ical_port']['1'] = 'Please enter the CURL proxy port.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_filter_event_title']['0'] = 'Filter event title';
$GLOBALS['TL_LANG']['tl_calendar']['ical_filter_event_title']['1'] = 'Please enter a string to be filtered in the title of the event.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_pattern_event_title']['0'] = 'Pattern event title';
$GLOBALS['TL_LANG']['tl_calendar']['ical_pattern_event_title']['1'] = 'Please enter a string to search for in the title of the event - see: preg_replace.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_replacement_event_title']['0'] = 'Replacement event title';
$GLOBALS['TL_LANG']['tl_calendar']['ical_replacement_event_title']['1'] = 'Please enter a string to replace in the title of the event - see: preg_replace.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_cache']['0'] = 'Calendar cache in seconds';
$GLOBALS['TL_LANG']['tl_calendar']['ical_cache']['1'] = 'Please enter the minimum number of seconds to cache the calender data. The calendar data will be rebuilt from the iCal source when the cache time is up.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_timezone']['0'] = 'Timezone';
$GLOBALS['TL_LANG']['tl_calendar']['ical_timezone']['1'] = 'Please select a timezone that should be used if the calendar doesn\'t contain a timezone.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_start']['0'] = 'Start date';
$GLOBALS['TL_LANG']['tl_calendar']['ical_start']['1'] = 'Please enter the start date of the calendar. If you do not enter a date, the actual date will be taken as start date.';

$GLOBALS['TL_LANG']['tl_calendar']['ical_end']['0'] = 'End date';
$GLOBALS['TL_LANG']['tl_calendar']['ical_end']['1'] = 'Please enter the end date of the calendar. If you do not enter a date, the date in one year will be taken as end date.';
