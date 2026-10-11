<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Util;

use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtil;
use PHPUnit\Framework\TestCase;

final class TimezoneUtilTest extends TestCase
{
    public function testGroupsTimezonesByRegion(): void
    {
        $timezones = (new TimezoneUtil())->getTimezones();

        $this->assertArrayHasKey('Europe', $timezones);
        $this->assertSame('Europe/Berlin', $timezones['Europe']['Europe/Berlin']);
        $this->assertArrayHasKey('America', $timezones);
        $this->assertArrayHasKey('America/New_York', $timezones['America']);
    }

    public function testKeepsNestedIdentifiersInTheirRegion(): void
    {
        $timezones = (new TimezoneUtil())->getTimezones();

        $this->assertArrayHasKey('America/Argentina/Buenos_Aires', $timezones['America']);
    }

    public function testEveryIdentifierIsListedExactlyOnce(): void
    {
        $timezones = (new TimezoneUtil())->getTimezones();
        $count = array_sum(array_map(count(...), $timezones));

        $this->assertSame(\count(\DateTimeZone::listIdentifiers()), $count);
    }
}
