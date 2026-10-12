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

use Cgoit\ContaoCalendarIcalBundle\Import\IcsFileImporter;
use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Cgoit\ContaoCalendarIcalBundle\Import\TimezoneCheck;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Cgoit\ContaoCalendarIcalBundle\Util\UploadedIcsFileResolver;
use Contao\CalendarModel;
use Contao\Date;
use Kigkonsult\Icalcreator\IcalInterface;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\Filesystem\Filesystem;

final class IcsFileImporterTest extends ContaoTestCase
{
    private const string ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:test\r\nBEGIN:VEVENT\r\nUID:1\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20260105T100000Z\r\nSUMMARY:Test\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();
        $this->projectDir = $this->getTempDir();
        (new Filesystem())->mkdir($this->projectDir.'/system/tmp');
    }

    public function testLoadsAnUploadedFile(): void
    {
        $this->write('system/tmp/club.ics', self::ICS);

        $cal = $this->importer()->load('system/tmp/club.ics', $this->calendar());

        $this->assertNotFalse($cal->getComponent(IcalInterface::VEVENT));
    }

    public function testRejectsPathsOutsideOfTheUploadDirectory(): void
    {
        $this->write('files/secret.ics', self::ICS);

        $this->expectException(\InvalidArgumentException::class);

        $this->importer()->load('files/secret.ics', $this->calendar());
    }

    public function testRejectsEmptyFiles(): void
    {
        $this->write('system/tmp/empty.ics', '');

        $this->expectException(\InvalidArgumentException::class);

        $this->importer()->load('system/tmp/empty.ics', $this->calendar());
    }

    public function testChecksTheTimezoneOfTheFile(): void
    {
        $importer = $this->importer();

        $this->assertSame(TimezoneCheck::Missing, $importer->checkTimezone($this->calendarWithTimezone(null)));
        $this->assertSame(TimezoneCheck::Matches, $importer->checkTimezone($this->calendarWithTimezone('Europe/Berlin')));
        $this->assertSame(TimezoneCheck::Differs, $importer->checkTimezone($this->calendarWithTimezone('America/New_York')));
    }

    public function testImportsWithTheTimezoneOfTheFile(): void
    {
        $this->assertSame(['X-WR-TIMEZONE', 'America/New_York'], $this->importedTimezone($this->calendarWithTimezone('America/New_York'), null, false));
    }

    public function testImportsWithTheChosenTimezoneIfTheFileHasNone(): void
    {
        $this->assertSame(['Europe/Paris', 'Europe/Paris'], $this->importedTimezone($this->calendarWithTimezone(null), 'Europe/Paris', false));
    }

    public function testImportsWithTheTimezoneOfTheSystemIfRequested(): void
    {
        $tz = $this->importedTimezone($this->calendarWithTimezone('America/New_York'), null, true);

        $this->assertSame('Europe/Berlin', $tz[1]);
    }

    public function testImportsWithoutTimezoneIfNeitherFileNorUserTellsOne(): void
    {
        $this->assertFalse($this->importedTimezone($this->calendarWithTimezone(null), null, false));
    }

    public function testDeletesTheUploadedFileAfterTheImport(): void
    {
        $this->write('system/tmp/club.ics', self::ICS);
        $importer = $this->importer($this->createStub(IcsImport::class));

        $importer->import('system/tmp/club.ics', $this->calendarWithTimezone(null), $this->calendar(), new Date(20260101, 'Ymd'), new Date(20261231, 'Ymd'), null, false, false, 0, null);

        $this->assertFileDoesNotExist($this->projectDir.'/system/tmp/club.ics');
    }

    public function testDeletesTheUploadedFileIfTheImportFails(): void
    {
        $this->write('system/tmp/club.ics', self::ICS);
        $icsImport = $this->createStub(IcsImport::class);
        $icsImport
            ->method('importFromIcsFile')
            ->willThrowException(new \RuntimeException('boom'))
        ;

        try {
            $this->importer($icsImport)->import('system/tmp/club.ics', $this->calendarWithTimezone(null), $this->calendar(), new Date(20260101, 'Ymd'), new Date(20261231, 'Ymd'), null, false, false, 0, null);
            $this->fail('The exception was expected');
        } catch (\RuntimeException) {
        }

        $this->assertFileDoesNotExist($this->projectDir.'/system/tmp/club.ics');
    }

    public function testDoesNotDeleteFilesOutsideOfTheUploadDirectory(): void
    {
        $this->write('files/keep.ics', self::ICS);

        $this->importer()->delete('files/keep.ics');

        $this->assertFileExists($this->projectDir.'/files/keep.ics');
    }

    /**
     * @return array<mixed>|false
     */
    private function importedTimezone(Vcalendar $cal, string|null $timezone, bool $useSystemTimezone): array|false
    {
        $this->write('system/tmp/club.ics', self::ICS);
        $received = null;

        $icsImport = $this->createMock(IcsImport::class);
        $icsImport
            ->expects($this->once())
            ->method('importFromIcsFile')
            ->willReturnCallback(
                static function (Vcalendar $cal, CalendarModel $calendar, Date $start, Date $end, array|bool $tz) use (&$received): void {
                    $received = $tz;
                },
            )
        ;

        $this->importer($icsImport)->import('system/tmp/club.ics', $cal, $this->calendar(), new Date(20260101, 'Ymd'), new Date(20261231, 'Ymd'), $timezone, $useSystemTimezone, false, 0, null);

        return $received;
    }

    private function importer(IcsImport|null $icsImport = null): IcsFileImporter
    {
        return new IcsFileImporter($icsImport ?? $this->createStub(IcsImport::class), new UploadedIcsFileResolver($this->projectDir), $this->projectDir);
    }

    private function calendar(): CalendarModel
    {
        $calendar = new CalendarModel();
        $calendar->id = 1;
        $calendar->title = 'Club';

        return $calendar;
    }

    private function calendarWithTimezone(string|null $timezone): Vcalendar
    {
        $cal = new Vcalendar();

        if (null !== $timezone) {
            $cal->setXprop(IcalInterface::X_WR_TIMEZONE, $timezone);
        }

        return $cal;
    }

    private function write(string $path, string $content): void
    {
        (new Filesystem())->dumpFile($this->projectDir.'/'.$path, $content);
    }
}
