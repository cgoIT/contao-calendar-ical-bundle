<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Import\Csv;

use Cgoit\ContaoCalendarIcalBundle\Import\Csv\CsvParser;
use Cgoit\ContaoCalendarIcalBundle\Import\Csv\CsvReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class CsvParserTest extends TestCase
{
    private string $file;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->file = $this->filesystem->tempnam(sys_get_temp_dir(), 'ical-csv-');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->file);
    }

    public function testReadsHeaderAndRowsWithCommaSeparator(): void
    {
        $this->filesystem->dumpFile($this->file, "title,start,end\nFirst,2024-01-01,2024-01-02\nSecond,2024-02-01,2024-02-02\n");

        $parser = new CsvParser($this->file);

        $this->assertSame(['title', 'start', 'end'], $parser->extractHeader());
        $this->assertSame(['First', '2024-01-01', '2024-01-02'], $parser->getDataArray());
        $this->assertSame(['Second', '2024-02-01', '2024-02-02'], $parser->getDataArray());
        $this->assertFalse($parser->getDataArray());
    }

    public function testDetectsSemicolonSeparator(): void
    {
        $this->filesystem->dumpFile($this->file, "title;start\nFirst;2024-01-01\n");

        $parser = new CsvParser($this->file);

        $this->assertSame(['title', 'start'], $parser->extractHeader());
        $this->assertSame(['First', '2024-01-01'], $parser->getDataArray());
    }

    public function testReadsSeveralRowsAtOnce(): void
    {
        $this->filesystem->dumpFile($this->file, "title,start\nA,1\nB,2\nC,3\n");

        $parser = new CsvParser($this->file);
        $parser->extractHeader();

        $this->assertSame([['A', '1'], ['B', '2']], $parser->getDataArray(2));
    }

    public function testConvertsLatin1Encoding(): void
    {
        $this->filesystem->dumpFile($this->file, "title,start\n".mb_convert_encoding('Köln', 'ISO-8859-1', 'UTF-8').",1\n");

        $parser = new CsvParser($this->file, 'iso-8859-1');
        $parser->extractHeader();

        $this->assertSame(['Köln', '1'], $parser->getDataArray());
    }

    public function testReaderIteratesOverAllLines(): void
    {
        $this->filesystem->dumpFile($this->file, "a,b\nc,d\n");

        $rows = iterator_to_array(new CsvReader($this->file));

        $this->assertSame([['a', 'b'], ['c', 'd']], $rows);
    }
}
