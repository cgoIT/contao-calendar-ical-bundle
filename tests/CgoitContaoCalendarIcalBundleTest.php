<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle;

use PHPUnit\Framework\TestCase;

class CgoitContaoCalendarIcalBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new CgoitContaoCalendarIcalBundle();

        $this->assertInstanceOf(CgoitContaoCalendarIcalBundle::class, $bundle);
    }
}
