<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace contao\dca;

use Contao\Controller;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_content']['palettes']['ical'] = '{type_legend},type,headline;{calendar_legend},ical_calendar,ical_title,ical_description,ical_prefix,ical_start,ical_end;{link_legend},linkTitle,ical_download_template;{protected_legend:hide},protected;{expert_legend},{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_calendar'] = [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => ['mandatory' => true, 'multiple' => true],
    'sql' => ['type' => 'blob', 'length' => 65535, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_title'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50 clr'],
    'sql' => ['type' => 'text', 'length' => 1000, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_description'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'textarea',
    'eval' => ['maxlength' => 1024, 'rows' => 4, 'allowHtml' => false, 'decodeEntities' => true, 'tl_class' => 'clr'],
    'sql' => ['type' => 'text', 'length' => 1024, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_prefix'] = [
    'exclude' => true,
    'search' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 128, 'tl_class' => 'w50 clr'],
    'sql' => ['type' => 'text', 'length' => 1000, 'notnull' => false],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_start'] = [
    'default' => time(),
    'exclude' => true,
    'filter' => true,
    'flag' => DataContainer::SORT_MONTH_DESC,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 clr'],
    'sql' => ['type' => 'string', 'length' => 12, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_end'] = [
    'default' => time() + 365 * 24 * 3600,
    'exclude' => true,
    'filter' => true,
    'flag' => DataContainer::SORT_MONTH_DESC,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'maxlength' => 10, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'string', 'length' => 12, 'default' => ''],
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ical_download_template'] = [
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static fn () => Controller::getTemplateGroup('ce_download_', [], 'ce_download'),
    'eval' => ['chosen' => true, 'tl_class' => 'w50'],
    'sql' => ['type' => 'text', 'length' => 1000, 'notnull' => false],
];
