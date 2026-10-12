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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
}
