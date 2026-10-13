<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests;

use Contao\ArticleModel;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\Model\Registry;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for tests that need Contao's legacy classes (models, Config, ...) without a
 * database or a booted kernel. It provides a minimal container with an empty DCA cache.
 */
abstract class ContaoTestCase extends TestCase
{
    private string|null $tempDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->tempDir) {
            (new Filesystem())->remove($this->tempDir);
            $this->tempDir = null;
        }

        System::setContainer(new ContainerBuilder());
        Registry::getInstance()->reset();
        unset($GLOBALS['TL_HOOKS']);

        parent::tearDown();
    }

    protected function getTempDir(): string
    {
        if (null === $this->tempDir) {
            $this->tempDir = sys_get_temp_dir().'/cgoit-ical-tests-'.bin2hex(random_bytes(6));
            (new Filesystem())->mkdir($this->tempDir);
        }

        return $this->tempDir;
    }

    /**
     * Sets up a container so that Contao models can be instantiated without a database.
     *
     * @param array<string, mixed> $parameters
     */
    protected function bootContao(array $parameters = []): ContainerBuilder
    {
        $cacheDir = $this->getTempDir().'/cache';
        (new Filesystem())->mkdir($cacheDir.'/contao/sql');

        // An empty DCA extract per table is enough for models that are filled by hand
        $filesystem = new Filesystem();

        foreach (['tl_calendar', 'tl_calendar_events', 'tl_content', 'tl_module', 'tl_article', 'tl_page'] as $table) {
            $filesystem->dumpFile($cacheDir.'/contao/sql/'.$table.'.php', "<?php\n");
        }

        // Column cast types are normally read from the database schema; values are set typed by hand
        (new Filesystem())->mkdir($cacheDir.'/contao/config');
        $filesystem->dumpFile($cacheDir.'/contao/config/column-types.php', "<?php return ['tl_calendar' => [], 'tl_calendar_events' => [], 'tl_content' => [], 'tl_module' => [], 'tl_article' => [], 'tl_page' => []];\n");

        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', $cacheDir);
        $container->setParameter('kernel.project_dir', $this->getTempDir());
        $container->setParameter('kernel.debug', false);

        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        $container->set('request_stack', new RequestStack());

        System::setContainer($container);

        // Initializes the legacy configuration (defaults) once
        Config::getInstance();
        $GLOBALS['TL_CONFIG']['timeZone'] = 'Europe/Berlin';
        $GLOBALS['TL_MODELS']['tl_calendar'] = CalendarModel::class;
        $GLOBALS['TL_MODELS']['tl_calendar_events'] = CalendarEventsModel::class;
        $GLOBALS['TL_MODELS']['tl_content'] = ContentModel::class;
        $GLOBALS['TL_MODELS']['tl_module'] = ModuleModel::class;
        $GLOBALS['TL_MODELS']['tl_article'] = ArticleModel::class;
        $GLOBALS['TL_MODELS']['tl_page'] = PageModel::class;

        return $container;
    }
}
