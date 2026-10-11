<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\ContaoManager;

use Cgoit\ContaoCalendarIcalBundle\CgoitContaoCalendarIcalBundle;
use Cgoit\ContaoCalendarIcalBundle\ContaoManager\Plugin;
use Contao\CalendarBundle\ContaoCalendarBundle;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testRegistersBundleAfterCalendarBundle(): void
    {
        $bundles = (new Plugin())->getBundles($this->createMock(ParserInterface::class));

        $this->assertCount(1, $bundles);
        $this->assertSame(CgoitContaoCalendarIcalBundle::class, $bundles[0]->getName());
        $this->assertSame([ContaoCalendarBundle::class], $bundles[0]->getLoadAfter());
        $this->assertSame(['contao-calendar-ical-bundle'], $bundles[0]->getReplace());
    }
}
