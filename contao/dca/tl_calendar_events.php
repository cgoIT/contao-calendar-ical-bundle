<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

/*
 * Table tl_calendar_events
 */
$GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['import'] = [
    'label' => &$GLOBALS['TL_LANG']['MSC']['import_calendar'],
    'class' => 'header_import header_icon',
    // The link to the import route is created by the ImportButtonCallback
];

$GLOBALS['TL_DCA']['tl_calendar_events']['fields']['ical_uuid'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'long'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];
