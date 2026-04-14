<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Contao\CalendarModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Idna;
use Contao\StringUtil;
use Contao\System;

#[AsCallback(table: 'tl_calendar_events', target: 'list.sorting.header')]
class CalendarHeaderCallback
{
    /**
     * @param array<mixed> $labels
     *
     * @return array<mixed>
     */
    public function __invoke(array $labels, DataContainer $dc): array
    {
        if (!$dc->id) {
            return $labels;
        }

        System::loadLanguageFile('tl_calendar');

        $objCalendar = CalendarModel::findById($dc->id);
        if (null !== $objCalendar) {
            if (!empty($objCalendar->make_ical)) {
                $shareDir = StringUtil::stripRootDir(System::getContainer()->getParameter('contao.web_dir').'/share');
                $file = $shareDir.'/'.$objCalendar->ical_alias.'.ics';
                $icsUrl = Idna::decode(Environment::get('base')).str_replace($shareDir, 'share', $file);

                $labels[$GLOBALS['TL_LANG']['tl_calendar']['ical_export']] = $icsUrl;
            }
            if (!empty($objCalendar->ical_source)) {
                $labels[$GLOBALS['TL_LANG']['tl_calendar']['ical_import']] = $objCalendar->ical_url;
            }
        }

        return $labels;
    }
}
