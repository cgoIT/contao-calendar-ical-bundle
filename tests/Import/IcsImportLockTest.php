<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Import;

use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Import\RemoteIcsDownloader;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Kigkonsult\Icalcreator\Vcalendar;
use Psr\Log\NullLogger;

/**
 * Covers the cache time and the lock of the web import with a real (SQLite) database for the lock columns.
 * The import of the events itself is replaced.
 */
final class IcsImportLockTest extends ContaoTestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\nEND:VCALENDAR\r\n";

    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->bootContao();
        $container->set('monolog.logger.contao.general', new NullLogger());

        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->db->executeStatement("CREATE TABLE tl_calendar (id INTEGER PRIMARY KEY, tstamp INTEGER NOT NULL DEFAULT 0, title TEXT, ical_importing TEXT NOT NULL DEFAULT '', ical_last_sync INTEGER DEFAULT NULL, ical_cache INTEGER NOT NULL DEFAULT 0)");
    }

    public function testDoesNothingWhileCacheIsValid(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 60, 'ical_cache' => 3600]);
        $import = $this->import();

        $this->assertFalse($import->importIcsForCalendar($calendar));

        $this->assertSame(0, $import->imports);
        $this->assertSame('', $this->row()['ical_importing']);
    }

    public function testImportsWhenCacheIsOver(): void
    {
        $lastSync = time() - 7200;
        $calendar = $this->calendar(['ical_last_sync' => $lastSync, 'ical_cache' => 3600]);
        $import = $this->import();

        $this->assertTrue($import->importIcsForCalendar($calendar));

        $this->assertSame(1, $import->imports);
        $this->assertGreaterThan(time() - 5, (int) $this->row()['ical_last_sync']);
        $this->assertSame('', $this->row()['ical_importing']);
        $this->assertGreaterThan(time() - 5, (int) $calendar->ical_last_sync);
    }

    public function testImportsNewSourceWithoutLastSync(): void
    {
        $import = $this->import();

        $import->importIcsForCalendar($this->calendar(['ical_last_sync' => null]));

        $this->assertSame(1, $import->imports);
    }

    public function testForceIgnoresCacheTime(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 60, 'ical_cache' => 3600]);
        $import = $this->import();

        $import->importIcsForCalendar($calendar, true);

        $this->assertSame(1, $import->imports);
    }

    public function testRunningImportBlocksParallelImportEvenIfForced(): void
    {
        $calendar = $this->calendar(['ical_importing' => '1', 'tstamp' => time() - 10]);
        $import = $this->import();

        $this->assertFalse($import->importIcsForCalendar($calendar, true));

        $this->assertSame(0, $import->imports);
        $this->assertSame('1', $this->row()['ical_importing'], 'The lock of the other process must stay untouched');
    }

    public function testLockIsTakenAtomicallyInTheDatabase(): void
    {
        // Two processes loaded the calendar before one of them took the lock
        $first = $this->calendar();
        $second = $this->calendar();
        $import = $this->import();

        $this->assertTrue($import->acquire($first));
        $this->assertFalse($import->acquire($second), 'The second process must not get the lock although its model says "not importing"');
        $this->assertSame('1', $this->row()['ical_importing']);
    }

    public function testStaleLockIsTakenOver(): void
    {
        $calendar = $this->calendar(['ical_importing' => '1', 'tstamp' => time() - 3600]);
        $import = $this->import();

        $import->importIcsForCalendar($calendar, true);

        $this->assertSame(1, $import->imports);
        $this->assertSame('', $this->row()['ical_importing']);
    }

    public function testLockIsReleasedAfterFailureAndNextRunRetries(): void
    {
        $lastSync = time() - 7200;
        $calendar = $this->calendar(['ical_last_sync' => $lastSync, 'ical_cache' => 3600]);
        $import = $this->import(throw: true);

        $this->assertTrue($import->importIcsForCalendar($calendar));

        $this->assertSame('', $this->row()['ical_importing']);
        $this->assertSame($lastSync, (int) $this->row()['ical_last_sync']);
    }

    public function testFailedDownloadDoesNotUpdateLastSync(): void
    {
        $lastSync = time() - 7200;
        $calendar = $this->calendar(['ical_last_sync' => $lastSync, 'ical_cache' => 3600]);
        $import = $this->import(content: null);

        $import->importIcsForCalendar($calendar);

        $this->assertSame(0, $import->imports);
        $this->assertSame($lastSync, (int) $this->row()['ical_last_sync']);
        $this->assertSame('', $this->row()['ical_importing']);
    }

    public function testOnlyLockColumnsAreWritten(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 7200, 'ical_cache' => 3600]);
        $import = $this->import();

        // An editor changes the title while the import is running
        $import->beforeImport = fn () => $this->db->executeStatement("UPDATE tl_calendar SET title = 'Changed by editor' WHERE id = 1");

        $import->importIcsForCalendar($calendar);

        $this->assertSame('Changed by editor', $this->row()['title']);
    }

    public function testCalendarWithoutSourceIsIgnored(): void
    {
        $import = $this->import();

        $this->assertFalse($import->importIcsForCalendar($this->calendar(['ical_source' => '']), true));

        $this->assertSame(0, $import->imports);
    }

    public function testEventsAreImportedInOneTransaction(): void
    {
        $this->calendar();
        $import = $this->import();
        $now = new Date(time(), 'Y-m-d');

        try {
            $import->importFromIcsFileUsingRealTransaction(new Vcalendar(), $this->calendar(), $now, $now, true);
            $this->fail('The error must not be swallowed');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_calendar WHERE id = 2'), 'a failing import is rolled back');

        $import->importFromIcsFileUsingRealTransaction(new Vcalendar(), $this->calendar(), $now, $now, false);

        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_calendar WHERE id = 2'), 'a successful import is committed');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return $this->db->fetchAssociative('SELECT * FROM tl_calendar WHERE id = 1') ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function calendar(array $data = []): CalendarModel
    {
        $values = array_merge(
            [
                'id' => 1,
                'title' => 'Web calendar',
                'tstamp' => time() - 86400,
                'ical_source' => '1',
                'ical_url' => 'https://example.com/cal.ics',
                'ical_cache' => 3600,
                'ical_last_sync' => null,
                'ical_importing' => '',
                'ical_timezone' => 'Europe/Berlin',
            ],
            $data,
        );

        if (0 === (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_calendar WHERE id = 1')) {
            $this->db->insert('tl_calendar', [
                'id' => 1,
                'title' => $values['title'],
                'tstamp' => $values['tstamp'],
                'ical_importing' => $values['ical_importing'],
                'ical_last_sync' => $values['ical_last_sync'],
                'ical_cache' => $values['ical_cache'],
            ]);
        }

        $calendar = new CalendarModel();

        foreach ($values as $key => $value) {
            $calendar->{$key} = $value;
        }

        return $calendar;
    }

    private function import(bool $throw = false, string|null $content = self::ICS): TestableIcsImport
    {
        $downloader = $this->createStub(RemoteIcsDownloader::class);
        $downloader
            ->method('download')
            ->willReturn($content)
        ;

        $import = new TestableIcsImport($throw);

        $reflection = new \ReflectionClass(IcsImport::class);
        $reflection->getProperty('downloader')->setValue($import, $downloader);
        $reflection->getProperty('defaultEndTimeDifference')->setValue($import, 365);
        $reflection->getProperty('db')->setValue($import, $this->db);
        $import->connection = $this->db;
        $reflection->getProperty('contaoFramework')->setValue($import, $this->createStub(ContaoFramework::class));

        return $import;
    }
}
