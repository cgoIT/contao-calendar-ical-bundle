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
use Contao\Date;
use Kigkonsult\Icalcreator\Vcalendar;
use Psr\Log\NullLogger;

/**
 * Covers the cache time and the lock of the web import. The database access of the model and of
 * the actual import is replaced.
 */
final class IcsImportLockTest extends ContaoTestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\nEND:VCALENDAR\r\n";

    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->bootContao();
        $container->set('monolog.logger.contao.general', new NullLogger());
    }

    public function testDoesNothingWhileCacheIsValid(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 60, 'ical_cache' => 3600]);
        $import = $this->import();

        $import->importIcsForCalendar($calendar);

        $this->assertSame(0, $import->imports);
        $this->assertSame(0, $calendar->saves);
    }

    public function testImportsWhenCacheIsOver(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 7200, 'ical_cache' => 3600]);
        $import = $this->import();

        $import->importIcsForCalendar($calendar);

        $this->assertSame(1, $import->imports);
        $this->assertGreaterThan(time() - 5, (int) $calendar->ical_last_sync);
        $this->assertSame('', $calendar->ical_importing);
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

        $import->importIcsForCalendar($calendar, true);

        $this->assertSame(0, $import->imports);
        $this->assertSame('1', $calendar->ical_importing, 'The lock of the other process must stay untouched');
    }

    public function testStaleLockIsIgnored(): void
    {
        $calendar = $this->calendar(['ical_importing' => '1', 'tstamp' => time() - 3600]);
        $import = $this->import();

        $import->importIcsForCalendar($calendar, true);

        $this->assertSame(1, $import->imports);
        $this->assertSame('', $calendar->ical_importing);
    }

    public function testLockIsReleasedAfterFailureAndNextRunRetries(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 7200, 'ical_cache' => 3600]);
        $lastSync = $calendar->ical_last_sync;
        $import = $this->import(throw: true);

        $import->importIcsForCalendar($calendar);

        $this->assertSame('', $calendar->ical_importing);
        $this->assertSame($lastSync, $calendar->ical_last_sync);
    }

    public function testFailedDownloadDoesNotUpdateLastSync(): void
    {
        $calendar = $this->calendar(['ical_last_sync' => time() - 7200, 'ical_cache' => 3600]);
        $lastSync = $calendar->ical_last_sync;
        $import = $this->import(content: null);

        $import->importIcsForCalendar($calendar);

        $this->assertSame(0, $import->imports);
        $this->assertSame($lastSync, $calendar->ical_last_sync);
        $this->assertSame('', $calendar->ical_importing);
    }

    public function testCalendarWithoutSourceIsIgnored(): void
    {
        $import = $this->import();

        $import->importIcsForCalendar($this->calendar(['ical_source' => '']), true);

        $this->assertSame(0, $import->imports);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function calendar(array $data = []): CalendarModel
    {
        $calendar = new class() extends CalendarModel {
            public int $saves = 0;

            public function save(): static
            {
                ++$this->saves;

                return $this;
            }
        };

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

        foreach ($values as $key => $value) {
            $calendar->{$key} = $value;
        }

        return $calendar;
    }

    /**
     * @return IcsImport&object{imports: int}
     */
    private function import(bool $throw = false, string|null $content = self::ICS): IcsImport
    {
        $downloader = $this->createStub(RemoteIcsDownloader::class);
        $downloader
            ->method('download')
            ->willReturn($content)
        ;

        $import = new class($throw) extends IcsImport {
            public int $imports = 0;

            public function __construct(private readonly bool $throw)
            {
            }

            public function importFromIcsFile(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
            {
                if ($this->throw) {
                    throw new \RuntimeException('import failed');
                }

                ++$this->imports;
            }
        };

        $reflection = new \ReflectionClass(IcsImport::class);
        $reflection->getProperty('downloader')->setValue($import, $downloader);
        $reflection->getProperty('defaultEndTimeDifference')->setValue($import, 365);

        return $import;
    }
}
