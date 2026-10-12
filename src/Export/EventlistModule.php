<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Export;

use Contao\Date;
use Contao\Events;

/**
 * Stands in for the module of an event list when the events of a module or content element are collected.
 *
 * The events generator passes a module to the "getAllEvents" hooks (e.g. of the calendar-extended-bundle),
 * which expect the settings of the event list as properties. The module is only a container for the row
 * and a bridge to the period calculation of the Contao core.
 *
 * @internal
 */
final class EventlistModule extends Events
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(array $row)
    {
        // The parent constructor needs a module model, content elements are supported as well
        $this->arrData = $row;
    }

    /**
     * @return array{0: int, 1: int, 2: string} The beginning, the end and the message for an empty period
     */
    public function getPeriod(Date $date, string $format): array
    {
        return $this->getDatesFromFormat($date, $format);
    }

    protected function compile(): void
    {
    }
}
