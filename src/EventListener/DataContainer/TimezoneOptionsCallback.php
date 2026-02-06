<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtil;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

#[AsCallback(table: 'tl_calendar', target: 'fields.ical_timezone.options')]
class TimezoneOptionsCallback
{
    public function __construct(private readonly TimezoneUtil $timezoneUtil)
    {
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(DataContainer|null $dc): array
    {
        return $this->timezoneUtil->getTimezones();
    }
}
