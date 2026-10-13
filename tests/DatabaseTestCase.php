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

use Ausi\SlugGenerator\SlugGenerator;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Import\RemoteIcsDownloader;
use Contao\BackendUser;
use Contao\CalendarModel;
use Contao\CoreBundle\DataContainer\VirtualFieldsHandler;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\CoreBundle\Slug\Slug;
use Contao\Database;
use Contao\Date;
use Contao\DcaLoader;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Kigkonsult\Icalcreator\Vcalendar;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Base class for the tests that run the importers and exporters against a real (SQLite) database, so that the models are
 * saved and read back. Calendar 1 exists; the back end user with the ID 7 is logged in.
 */
abstract class DatabaseTestCase extends ContaoTestCase
{
    protected Connection $db;

    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement('CREATE TABLE tl_calendar (id INTEGER PRIMARY KEY, tstamp INTEGER NOT NULL DEFAULT 0, title TEXT, jumpTo INTEGER NOT NULL DEFAULT 0, ical_publish_imported TEXT DEFAULT NULL, protected TEXT NOT NULL DEFAULT \'\', make_ical TEXT NOT NULL DEFAULT \'\', ical_alias TEXT NOT NULL DEFAULT \'\', ical_prefix TEXT NOT NULL DEFAULT \'\', ical_description TEXT DEFAULT NULL, ical_start INTEGER DEFAULT NULL, ical_end INTEGER DEFAULT NULL)');
        $this->db->executeStatement('CREATE TABLE tl_calendar_events (id INTEGER PRIMARY KEY, pid INTEGER NOT NULL DEFAULT 0, tstamp INTEGER NOT NULL DEFAULT 0, title TEXT, alias TEXT, source TEXT, teaser TEXT, start TEXT NOT NULL DEFAULT \'\', stop TEXT NOT NULL DEFAULT \'\', author INTEGER NOT NULL DEFAULT 0, addTime TEXT NOT NULL DEFAULT \'\', startTime INTEGER DEFAULT NULL, endTime INTEGER DEFAULT NULL, startDate INTEGER DEFAULT NULL, endDate INTEGER DEFAULT NULL, recurring TEXT NOT NULL DEFAULT \'\', repeatEach TEXT DEFAULT NULL, repeatEnd INTEGER NOT NULL DEFAULT 0, recurrences INTEGER NOT NULL DEFAULT 0, singleSRC BLOB DEFAULT NULL, published TEXT NOT NULL DEFAULT \'\', ical_uuid TEXT DEFAULT NULL)');
        $this->db->executeStatement('CREATE TABLE tl_content (id INTEGER PRIMARY KEY, pid INTEGER NOT NULL DEFAULT 0, ptable TEXT, sorting INTEGER NOT NULL DEFAULT 0, tstamp INTEGER NOT NULL DEFAULT 0, type TEXT, text TEXT)');
        $this->db->executeStatement("INSERT INTO tl_calendar (id, title) VALUES (1, 'Test')");

        $container = $this->bootContao(['kernel.charset' => 'UTF-8']);
        $container->set('database_connection', $this->db);

        // MySQL function used by the core models
        $native = $this->db->getNativeConnection();

        if ($native instanceof \PDO) {
            $findInSet = static fn (mixed $needle, mixed $list): int => false !== ($position = array_search((string) $needle, explode(',', (string) $list), true)) ? $position + 1 : 0;

            // PHP 8.4 moved the method to the driver specific class
            $method = method_exists($native, 'createFunction') ? 'createFunction' : 'sqliteCreateFunction';
            \call_user_func([$native, $method], 'FIND_IN_SET', $findInSet, 2);
        }
        $container->set('monolog.logger.contao.general', new NullLogger());

        $virtualFields = $this->createMock(VirtualFieldsHandler::class);
        $virtualFields
            ->method('expandFields')
            ->willReturnArgument(0)
        ;

        $virtualFields
            ->method('combineFields')
            ->willReturnArgument(0)
        ;
        $container->set('contao.data_container.virtual_fields_handler', $virtualFields);

        // Nobody is in the preview mode, so only published records are found
        $tokenChecker = $this->createStub(TokenChecker::class);
        $tokenChecker
            ->method('isPreviewMode')
            ->willReturn(false)
        ;
        $container->set('contao.security.token_checker', $tokenChecker);

        $cacheDir = $container->getParameter('kernel.cache_dir');
        $fields = ['pid', 'tstamp', 'title', 'alias', 'author', 'addTime', 'startTime', 'endTime', 'startDate', 'endDate', 'source', 'teaser', 'start', 'stop', 'recurring', 'repeatEach', 'repeatEnd', 'recurrences', 'singleSRC', 'published', 'ical_uuid'];
        $dca = "<?php\n\$GLOBALS['TL_DCA']['tl_calendar_events']['fields'] = ".var_export(array_fill_keys($fields, []), true).";\n";
        (new Filesystem())->dumpFile($cacheDir.'/contao/dca/tl_calendar_events.php', $dca);
        (new Filesystem())->dumpFile($cacheDir.'/contao/config/column-types.php', "<?php return ['tl_calendar' => [], 'tl_calendar_events' => ['startTime' => 'int', 'endTime' => 'int', 'startDate' => 'int', 'endDate' => 'int', 'id' => 'int', 'pid' => 'int'], 'tl_content' => [], 'tl_module' => [], 'tl_article' => [], 'tl_page' => []];\n");

        DcaLoader::reset();
        (new \ReflectionProperty(Database::class, 'objInstance'))->setValue(null, new SqliteDatabase($this->db));

        // The author of the imported events is the current back end user
        $user = (new \ReflectionClass(BackendUser::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BackendUser::class, 'arrData'))->setValue($user, ['id' => 7]);
        (new \ReflectionProperty(BackendUser::class, 'objInstance'))->setValue(null, $user);

        // The language files are not available here, the importer only needs the label of the location
        $GLOBALS['TL_LANGUAGE'] = 'en';
        $GLOBALS['TL_LANG']['MSC']['location'] = 'Location';
        $GLOBALS['TL_LANG']['tl_calendar_events']['untitled'] = 'Untitled';
        (new \ReflectionProperty(System::class, 'arrLanguageFiles'))->setValue(null, ['default' => ['en' => true]]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        (new \ReflectionProperty(BackendUser::class, 'objInstance'))->setValue(null, null);
        (new \ReflectionProperty(Database::class, 'objInstance'))->setValue(null, null);
        DcaLoader::reset();

        parent::tearDown();
    }

    protected function vevent(string $uid, string ...$lines): string
    {
        return "BEGIN:VEVENT\r\nUID:".$uid."\r\nDTSTAMP:20240101T000000Z\r\n".implode("\r\n", $lines)."\r\nEND:VEVENT\r\n";
    }

    protected function importIcs(string $events, bool $deleteCalendar = false, string|null $filter = null, string|null $pattern = null, string|null $replacement = null): void
    {
        $this->runImport($this->createImport(), $events, $deleteCalendar, $filter, $pattern, $replacement);
    }

    protected function createImport(EventDispatcher|null $dispatcher = null): IcsImport
    {
        $pageAdapter = $this->createMock(Adapter::class);
        $pageAdapter
            ->method('__call')
            ->willReturn(null)
        ;

        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturn($pageAdapter)
        ;

        return new IcsImport(
            $this->db,
            $this->createSlug(),
            $framework,
            365,
            $dispatcher ?? new EventDispatcher(),
            $this->createMock(RemoteIcsDownloader::class),
            new NullLogger(),
            new ParameterBag(['cgoit_contao_calendar_ical.max_repeat_count' => 365]),
        );
    }

    protected function runImport(IcsImport $import, string $events, bool $deleteCalendar = false, string|null $filter = null, string|null $pattern = null, string|null $replacement = null): void
    {
        $cal = new Vcalendar();
        $cal->parse("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\n".$events."END:VCALENDAR\r\n");

        $import->importFromIcsFile(
            $cal,
            CalendarModel::findById(1),
            new Date(mktime(0, 0, 0, 1, 1, 2024), 'Y-m-d'),
            new Date(mktime(0, 0, 0, 12, 31, 2024), 'Y-m-d'),
            ['Europe/Berlin', 'Europe/Berlin'],
            $filter,
            $pattern,
            $replacement,
            $deleteCalendar,
        );
    }

    protected function createFramework(): ContaoFramework
    {
        $pageAdapter = $this->createMock(Adapter::class);
        $pageAdapter
            ->method('__call')
            ->willReturn(null)
        ;

        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->method('getAdapter')
            ->willReturn($pageAdapter)
        ;

        return $framework;
    }

    protected function createSlug(): Slug
    {
        return new Slug(new SlugGenerator(), $this->createFramework());
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function events(): array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM tl_calendar_events ORDER BY id');
    }
}
