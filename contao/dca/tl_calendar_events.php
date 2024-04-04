<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace contao\dca;

/*
 * Table tl_calendar_events
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['export'] = [
    'label' => &$GLOBALS['TL_LANG']['MSC']['import_calendar'],
    'href' => 'key=import',
    'class' => 'header_import header_icon',
    'attributes' => 'onclick="Backend.getScrollOffset();"',
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['icssource'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['source'],
    'eval' => ['fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'ics,csv'],
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ical_uuid'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'long'],
    'sql' => "varchar(255) NOT NULL default ''",
];
