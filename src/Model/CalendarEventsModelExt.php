<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Model;

use Contao\CalendarEventsModel;
use Contao\Date;

class CalendarEventsModelExt extends CalendarEventsModel
{
    public static function findCurrentByPid($intPid, $intStart, $intEnd, array $arrOptions = [])
    {
        $t = static::$strTable;
        $intStart = (int) $intStart;
        $intEnd = (int) $intEnd;

        $arrColumns = ["$t.pid=? AND (($t.startTime>=$intStart AND $t.startTime<=$intEnd) OR ($t.endTime>=$intStart AND $t.endTime<=$intEnd) OR ($t.startTime<=$intStart AND $t.endTime>=$intEnd) OR (($t.recurringExt=1 OR $t.recurring=1) AND ($t.recurrences=0 OR $t.repeatEnd>=$intStart) AND $t.startTime<=$intEnd))"];

        if (isset($arrOptions['showFeatured'])) {
            if (true === $arrOptions['showFeatured']) {
                $arrColumns[] = "$t.featured=1";
            } elseif (false === $arrOptions['showFeatured']) {
                $arrColumns[] = "$t.featured=0";
            }
        }

        if (!static::isPreviewMode($arrOptions)) {
            $time = Date::floorToMinute();
            $arrColumns[] = "$t.published=1 AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time)";
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.startTime";
        }

        return static::findBy($arrColumns, [$intPid], $arrOptions);
    }

    public static function findUpcomingByPids($arrIds, $intLimit = 0, array $arrOptions = [])
    {
        if (empty($arrIds) || !\is_array($arrIds)) {
            return null;
        }

        $t = static::$strTable;
        $time = Date::floorToMinute();

        // Get upcoming events using endTime instead of startTime (see #3917)
        $arrColumns = ["$t.pid IN(".implode(',', array_map('\intval', $arrIds)).") AND $t.published=1 AND ($t.start='' OR $t.start<=$time) AND ($t.stop='' OR $t.stop>$time) AND ($t.endTime>=$time OR (($t.recurringExt=1 OR $t.recurring=1) AND ($t.recurrences=0 OR $t.repeatEnd>=$time)))"];

        if ($intLimit > 0) {
            $arrOptions['limit'] = $intLimit;
        }

        if (!isset($arrOptions['order'])) {
            $arrOptions['order'] = "$t.startTime";
        }

        return static::findBy($arrColumns, null, $arrOptions);
    }
}
