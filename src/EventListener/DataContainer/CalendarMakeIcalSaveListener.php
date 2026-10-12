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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;

/**
 * The subscription file is written to the public "share" directory, so it must not be
 * generated for protected calendars.
 */
#[AsCallback(table: 'tl_calendar', target: 'fields.make_ical.save')]
class CalendarMakeIcalSaveListener
{
    public function __invoke(mixed $value, DataContainer $dc): mixed
    {
        if (empty($value)) {
            return $value;
        }

        // The "protected" checkbox might be changed in the same request
        $protected = Input::post('protected') ?? $dc->getCurrentRecord()['protected'] ?? false;

        if (!empty($protected)) {
            throw new \RuntimeException($GLOBALS['TL_LANG']['ERR']['ical_protected_calendar']);
        }

        return $value;
    }
}
