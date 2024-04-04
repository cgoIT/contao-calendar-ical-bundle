<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Util;

class TimezoneUtil
{
    /**
     * @return array<mixed>
     */
    public function getTimezones(): array
    {
        $arrTz = [];

        foreach (\DateTimeZone::listIdentifiers() as $identifier) {
            $parts = explode('/', $identifier, 2);
            if (!\array_key_exists($parts[0], $arrTz)) {
                $arrTz[$parts[0]] = [];
            }
            $arrTz[$parts[0]][$identifier] = $identifier;
        }

        return $arrTz;
    }
}
