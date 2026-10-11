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

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Cgoit\ContaoCalendarIcalBundle\CgoitContaoCalendarIcalBundle;
use Cgoit\ContaoCalendarIcalBundle\DependencyInjection\CgoitContaoCalendarIcalExtension;
use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ExtensionTest extends TestCase
{
    public function testBundleProvidesExtension(): void
    {
        $this->assertInstanceOf(CgoitContaoCalendarIcalExtension::class, (new CgoitContaoCalendarIcalBundle())->getContainerExtension());
    }

    public function testExtensionAliasMatchesDocumentedConfigurationKey(): void
    {
        $this->assertSame('cgoit_contao_calendar_ical', (new CgoitContaoCalendarIcalExtension())->getAlias());
    }

    public function testLoadsServicesAndDefaultParameter(): void
    {
        $container = new ContainerBuilder();
        (new CgoitContaoCalendarIcalExtension())->load([], $container);

        $this->assertSame(365, $container->getParameter('calendar_ical.end_date_time_difference_in_days'));
        $this->assertTrue($container->hasDefinition(IcsExport::class));
        $this->assertTrue($container->hasDefinition(ExportController::class));
        $this->assertTrue($container->hasDefinition(TimezoneUtil::class));
    }

    public function testConfiguredValueIsExposedAsParameter(): void
    {
        $container = new ContainerBuilder();
        (new CgoitContaoCalendarIcalExtension())->load([['end_date_time_difference_in_days' => 10]], $container);

        $this->assertSame(10, $container->getParameter('calendar_ical.end_date_time_difference_in_days'));
    }

    public function testControllersUsedFromGlobalsAreRegisteredPublic(): void
    {
        $container = new ContainerBuilder();
        (new CgoitContaoCalendarIcalExtension())->load([], $container);

        foreach ([
            'Backend\ImportFileController',
            'Backend\Automator',
            'Controller\Route\EventlistIcsExportController',
            'Controller\Route\SingleEventIcsExportController',
        ] as $class) {
            $this->assertTrue($container->getDefinition('Cgoit\ContaoCalendarIcalBundle\\'.$class)->isPublic(), $class);
        }
    }
}
