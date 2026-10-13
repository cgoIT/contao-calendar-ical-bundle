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

use Cgoit\ContaoCalendarIcalBundle\Import\CsvImport;
use Cgoit\ContaoCalendarIcalBundle\Util\UploadedFileResolver;
use Contao\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The importer needs a database; the parsing of dates and times is tested directly.
 */
final class CsvImportTest extends TestCase
{
    private CsvImport $import;

    protected function setUp(): void
    {
        $this->import = (new \ReflectionClass(CsvImport::class))->newInstanceWithoutConstructor();
    }

    public function testReadsTheHeaderAndThePreviewOfAFile(): void
    {
        $dir = sys_get_temp_dir().'/cgoit-csv-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($dir.'/system/tmp/events.csv', "Titel;Datum;Ort\nTraining;01.05.2024;Halle\nSpiel;08.05.2024;Platz\n");
        $this->useDirectory($dir);

        $preview = $this->import->readPreview('system/tmp/events.csv', 'utf8', 5);

        $this->assertSame(['Titel', 'Datum', 'Ort'], $preview['header']);
        $this->assertSame([['Training', '01.05.2024', 'Halle'], ['Spiel', '08.05.2024', 'Platz']], $preview['rows']);

        (new Filesystem())->remove($dir);
    }

    public function testDoesNotReadFilesOutsideOfTheUploadDirectory(): void
    {
        $dir = sys_get_temp_dir().'/cgoit-csv-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($dir.'/files/events.csv', "Titel\nTraining\n");
        $this->useDirectory($dir);

        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->import->readPreview('files/events.csv', 'utf8');
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    public function testImportStopsBeforeAnythingIsDeletedIfTheFileHasNoHeader(): void
    {
        $dir = sys_get_temp_dir().'/cgoit-csv-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($dir.'/system/tmp/empty.csv', '');
        $this->useDirectory($dir);

        // The database is not set up: the import must fail before it touches the calendar
        $this->expectException(\InvalidArgumentException::class);

        try {
            $this->import->import('system/tmp/empty.csv', 1, [[0, 'title']], 'utf8', 'd.m.Y', 'H:i', new Date(20260101, 'Ymd'), null, true, 0, '');
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    public function testDeletesOnlyUploadedCsvFiles(): void
    {
        $dir = sys_get_temp_dir().'/cgoit-csv-'.bin2hex(random_bytes(4));
        $filesystem = new Filesystem();
        $filesystem->dumpFile($dir.'/system/tmp/events.csv', 'a');
        $filesystem->dumpFile($dir.'/files/events.csv', 'a');
        $this->useDirectory($dir);

        $this->import->delete('system/tmp/events.csv');
        $this->import->delete('files/events.csv');

        $this->assertFileDoesNotExist($dir.'/system/tmp/events.csv');
        $this->assertFileExists($dir.'/files/events.csv', 'Files outside of the upload directory must stay');

        $filesystem->remove($dir);
    }

    public function testParsesDates(): void
    {
        $this->assertSame(mktime(0, 0, 0, 5, 1, 2024), $this->call('parseDate', 'd.m.Y', '01.05.2024'));
        $this->assertSame(mktime(0, 0, 0, 5, 1, 2024), $this->call('parseDate', 'Y-m-d', ' 2024-05-01 '));
    }

    #[DataProvider('invalidDates')]
    public function testRejectsInvalidDates(string $format, string $value): void
    {
        $this->assertNull($this->call('parseDate', $format, $value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'wrong format' => ['d.m.Y', '2024-05-01'];
        yield 'not a date' => ['d.m.Y', 'tomorrow'];
        yield 'day does not exist' => ['d.m.Y', '31.02.2024'];
        yield 'trailing data' => ['d.m.Y', '01.05.2024 extra'];
        yield 'time instead of date' => ['d.m.Y', '10:00'];
    }

    public function testParsesTimes(): void
    {
        $this->assertSame(18 * 3600 + 30 * 60, $this->call('parseTime', 'H:i', '18:30'));
        $this->assertSame(18 * 3600 + 30 * 60 + 15, $this->call('parseTime', 'H:i:s', '18:30:15'));
    }

    #[DataProvider('invalidTimes')]
    public function testRejectsInvalidTimes(string $format, string $value): void
    {
        $this->assertNull($this->call('parseTime', $format, $value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidTimes(): iterable
    {
        yield 'not a time' => ['H:i', 'evening'];
        yield 'out of range' => ['H:i', '25:61'];
        yield 'wrong format' => ['H:i', '18.30'];
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(CsvImport::class, $method))->invoke($this->import, ...$arguments);
    }

    private function useDirectory(string $dir): void
    {
        $this->setProperty('projectDir', $dir);
        $this->setProperty('uploadedFileResolver', new UploadedFileResolver($dir));
    }

    private function setProperty(string $name, mixed $value): void
    {
        (new \ReflectionProperty(CsvImport::class, $name))->setValue($this->import, $value);
    }
}
