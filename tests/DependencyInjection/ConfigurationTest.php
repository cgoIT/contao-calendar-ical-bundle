<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\DependencyInjection;

use Cgoit\ContaoCalendarIcalBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultEndDateTimeDifferenceIs365Days(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertSame(365, (int) $config['end_date_time_difference_in_days']);
    }

    public function testAcceptsCustomValue(): void
    {
        $config = (new Processor())->processConfiguration(
            new Configuration(),
            [
                ['end_date_time_difference_in_days' => 30],
            ],
        );

        $this->assertSame(30, $config['end_date_time_difference_in_days']);
    }

    public function testRejectsValueBelowOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(
            new Configuration(),
            [
                ['end_date_time_difference_in_days' => 0],
            ],
        );
    }
}
