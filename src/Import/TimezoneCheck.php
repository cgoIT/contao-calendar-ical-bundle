<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import;

/**
 * Whether the time zone of an imported ics file needs to be confirmed or chosen by the user.
 */
enum TimezoneCheck
{
    /**
     * The file has the time zone of the system.
     */
    case Matches;

    /**
     * The file has a different time zone than the system.
     */
    case Differs;

    /**
     * The file does not tell its time zone.
     */
    case Missing;
}
