<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Cgoit\ContaoCalendarIcalBundle\Drivers\DC_IcalImport;

/*
 * Virtual table of the import form in the back end. There is no such table in the database, the form is
 * rendered with the widgets of the data container (see DC_IcalImport).
 */
$GLOBALS['TL_DCA']['tl_calendar_ical_import'] = [
    'config' => [
        'dataContainer' => DC_IcalImport::class,
    ],

    'palettes' => [
        'upload' => '{source_legend},icssource;{range_legend},startDate,endDate,timeshift;{options_legend},deleteCalendar,filterEventTitle',
        // The palette "csv" is composed at runtime, because it contains a field for every column of the file
        'timezone_differs' => '{timezone_legend},correctTimezone',
        'timezone_missing' => '{timezone_legend},timezone',
    ],

    'fields' => [
        'startDate' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importStartDate'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
        ],
        'endDate' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importEndDate'],
            'inputType' => 'text',
            'eval' => ['maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
        ],
        'timeshift' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importTimeShift'],
            'inputType' => 'text',
            'eval' => ['maxlength' => 4, 'rgxp' => 'digit', 'tl_class' => 'clr w50'],
        ],
        'deleteCalendar' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importDeleteCalendar'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr w50 m12'],
        ],
        'correctTimezone' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['correctTimezone'],
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'clr m12'],
        ],
        'timezone' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['timezone'],
            'inputType' => 'select',
            'eval' => ['mandatory' => true, 'chosen' => true, 'tl_class' => 'w50'],
        ],
        'encoding' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['encoding'],
            'inputType' => 'select',
            'options' => ['utf8' => 'UTF-8', 'latin1' => 'ISO-8859-1 (Windows)'],
            'eval' => ['mandatory' => true, 'tl_class' => 'clr w50'],
        ],
        'dateFormat' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importDateFormat'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 20, 'tl_class' => 'w50'],
        ],
        'timeFormat' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importTimeFormat'],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 20, 'tl_class' => 'w50'],
        ],
        'filterEventTitle' => [
            'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['importFilterEventTitle'],
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'clr w50'],
        ],
    ],
];
