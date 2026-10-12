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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

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

    public function testLoadsTheRoutesOfTheControllers(): void
    {
        $locator = new FileLocator(__DIR__.'/../../config');
        $resolver = new LoaderResolver();
        $resolver->addLoader(new YamlFileLoader($locator));
        $resolver->addLoader(new AttributeDirectoryLoader($locator, $this->createAttributeClassLoader()));

        $routes = (new Plugin())->getRouteCollection($resolver, $this->createStub(KernelInterface::class));

        $this->assertInstanceOf(RouteCollection::class, $routes);

        // The names and paths of these routes are public API
        $this->assertSame('/_event/ics-export/{id}', $routes->get('event_frontend_ics_export')?->getPath());
        $this->assertSame('/_eventlist/ics-export/{id}', $routes->get('eventlist_frontend_ics_export')?->getPath());
        $this->assertInstanceOf(Route::class, $routes->get('cgoit_calendar_ical_import'));
    }

    private function createAttributeClassLoader(): AttributeClassLoader
    {
        return new class() extends AttributeClassLoader {
            /**
             * @param \ReflectionClass<object> $class
             */
            protected function configureRoute(Route $route, \ReflectionClass $class, \ReflectionMethod $method, object $annot): void
            {
                $route->setDefault('_controller', $class->getName().'::'.$method->getName());
            }
        };
    }
}
