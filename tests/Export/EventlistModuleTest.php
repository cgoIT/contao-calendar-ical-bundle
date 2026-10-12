<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Export;

use Cgoit\ContaoCalendarIcalBundle\Export\EventlistModule;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\Date;

final class EventlistModuleTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();

        foreach (['cal_emptyDay', 'cal_emptyMonth', 'cal_emptyYear', 'cal_empty'] as $key) {
            $GLOBALS['TL_LANG']['MSC'][$key] = $key;
        }
    }

    public function testExposesTheRowAsProperties(): void
    {
        $module = new EventlistModule(['cal_noSpan' => '1', 'cal_format' => 'cal_month']);

        $this->assertSame('1', $module->cal_noSpan);
        $this->assertSame('cal_month', $module->cal_format);
        $this->assertTrue(isset($module->cal_format));
        $this->assertFalse(isset($module->unknown));
    }

    public function testCalculatesThePeriodOfTheFormat(): void
    {
        $module = new EventlistModule([]);
        $date = new Date(20240515, 'Ymd');

        $this->assertSame([$date->dayBegin, $date->dayEnd], \array_slice($module->getPeriod($date, 'cal_day'), 0, 2));
        $this->assertSame([$date->monthBegin, $date->monthEnd], \array_slice($module->getPeriod($date, 'cal_month'), 0, 2));
        $this->assertSame([$date->yearBegin, $date->yearEnd], \array_slice($module->getPeriod($date, 'cal_year'), 0, 2));
        $this->assertSame(0, $module->getPeriod($date, 'cal_all')[0]);
    }
}
