<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace contao\dca;

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()->addLegend('ical_legend', 'comments_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('make_ical', 'ical_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('ical_source', 'ical_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_calendar')
;

$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'] = array_merge(
    ['make_ical', 'ical_source'],
    $GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'],
);

$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['make_ical'] = 'ical_alias,ical_prefix,ical_description,ical_start,ical_end';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['ical_source'] = 'ical_url,ical_proxy,ical_bnpw,ical_port,ical_filter_event_title,ical_pattern_event_title,ical_replacement_event_title,ical_timezone,ical_cache,ical_source_start,ical_source_end';

$GLOBALS['TL_DCA']['tl_calendar']['fields']['make_ical'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_timezone'] = [
    'default' => 0,
    'exclude' => true,
    'filter' => true,
    'inputType' => 'select',
    'eval' => ['mandatory' => true, 'chosen' => true, 'includeBlankOption' => true, 'doNotCopy' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 128, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_source'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'tl_class' => 'clr m12'],
    'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_alias'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'alnum', 'unique' => true, 'spaceToUnderscore' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
    'sql' => ['type' => 'binary', 'length' => 128, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_prefix'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 128, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 128, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_description'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'textarea',
    'eval' => ['style' => 'height:60px;', 'tl_class' => 'clr'],
    'sql' => ['type' => 'text', 'length' => 65535, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_url'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'tl_class' => 'long'],
    'sql' => ['type' => 'text', 'length' => 1000, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_proxy'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'long'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_bnpw'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_port'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 32, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 32, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_filter_event_title'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'clr'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_pattern_event_title'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_replacement_event_title'] = [
    'exclude' => true,
    'filter' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 255, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_cache'] = [
    'default' => 86400,
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql' => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'default' => 86400],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_start'] = [
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'clr w50 wizard'],
    'sql' => ['type' => 'string', 'length' => 12, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_end'] = [
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => ['type' => 'string', 'length' => 12, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_source_start'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_start'],
    'default' => time(),
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'clr w50 wizard'],
    'sql' => ['type' => 'string', 'length' => 12, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_source_end'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_calendar']['ical_end'],
    'default' => time() + 365 * 24 * 3600,
    'exclude' => true,
    'filter' => true,
    'flag' => 8,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => ['type' => 'string', 'length' => 12, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_last_sync'] = [
    'sql' => ['type' => 'bigint', 'length' => 20, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['ical_importing'] = [
    'sql' => ['type' => 'string', 'length' => 1, 'fixed' => true, 'default' => ''],
];
