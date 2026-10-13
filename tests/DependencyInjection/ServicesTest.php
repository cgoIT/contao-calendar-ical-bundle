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

use Cgoit\ContaoCalendarIcalBundle\Backend\Automator;
use Cgoit\ContaoCalendarIcalBundle\Controller\Route\EventlistIcsExportController;
use Cgoit\ContaoCalendarIcalBundle\Export\EventlistModule;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Loads the service configuration with stubs for the services of Contao and Symfony, so that
 * a constructor argument that cannot be autowired is found without a booted kernel.
 */
final class ServicesTest extends TestCase
{
    public function testServicesCanBeAutowired(): void
    {
        $container = $this->createContainer();
        $container->compile();

        $this->assertTrue($container->hasDefinition(Automator::class));
        $this->assertTrue($container->hasDefinition(IcsImport::class));
    }

    public function testEntryPointsAreKeptPublicAndHelpersAreNoServices(): void
    {
        $container = $this->createContainer();

        $this->assertTrue($container->getDefinition(Automator::class)->isPublic());
        $this->assertTrue($container->getDefinition(EventlistIcsExportController::class)->isPublic());
        // Excluded classes are only registered for better error messages and removed when the container is compiled
        $this->assertTrue($container->getDefinition(EventlistModule::class)->hasTag('container.excluded'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/project');
        $container->setParameter('contao.web_dir', '/project/public');
        $container->setParameter('calendar_ical.end_date_time_difference_in_days', 365);

        $external = [
            RequestStack::class, Connection::class, ContaoFramework::class, InsertTagParser::class, Slug::class,
            ContaoCsrfTokenManager::class, LoggerInterface::class, EventDispatcherInterface::class,
            ParameterBagInterface::class, HttpClientInterface::class, UrlGeneratorInterface::class, ScopeMatcher::class, Security::class,
        ];

        foreach ($external as $id) {
            $container->setDefinition($id, (new Definition($id))->setSynthetic(true)->setPublic(true));
        }

        // Controllers get the container injected by the controller resolver in the application
        $container->setAlias(ContainerInterface::class, 'service_container');

        foreach (['monolog.logger.contao.cron' => LoggerInterface::class, 'monolog.logger.contao.general' => LoggerInterface::class] as $id => $class) {
            $container->setDefinition($id, (new Definition($class))->setSynthetic(true)->setPublic(true));
        }

        (new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config')))->load('services.yaml');

        // Unused private services are removed without checking them, so make all services public to find arguments that cannot be autowired
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        return $container;
    }
}
