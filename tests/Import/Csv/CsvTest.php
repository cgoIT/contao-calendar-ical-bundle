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

use Cgoit\ContaoCalendarIcalBundle\Import\Csv\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    /**
     * @param array<string> $expected
     */
    #[DataProvider('parseProvider')]
    public function testParseString(string $line, array $expected, string $separator = ','): void
    {
        $this->assertSame($expected, Csv::parseString($line, $separator));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function parseProvider(): iterable
    {
        yield 'empty line' => ['', []];
        yield 'plain values' => ['a,b,c', ['a', 'b', 'c']];
        yield 'quoted values' => ['"a","b"', ['a', 'b']];
        yield 'escaped quotes' => ['a,"the ""string"""', ['a', 'the "string"']];
        yield 'separator inside quotes' => ['"a,b",c', ['a,b', 'c']];
        yield 'semicolon separator' => ['a;"b;c";d', ['a', 'b;c', 'd'], ';'];
        yield 'empty values' => ['a,,c', ['a', '', 'c']];
        yield 'trailing line break is dropped' => ["a,b\r\n", ['a', 'b']];
    }

    public function testParseStringThrowsOnUnterminatedQuote(): void
    {
        $this->expectException(\Exception::class);

        Csv::parseString('a,"b');
    }

    #[DataProvider('escapeProvider')]
    public function testEscapeString(string $value, string $expected): void
    {
        $this->assertSame($expected, Csv::escapeString($value));
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function escapeProvider(): iterable
    {
        yield 'plain' => ['abc', 'abc'];
        yield 'comma' => ['a,b', '"a,b"'];
        yield 'quote' => ['say "hi"', '"say ""hi"""'];
        yield 'line break' => ["a\nb", "\"a\nb\""];
    }

    public function testEscapedValuesCanBeParsedAgain(): void
    {
        $values = ['plain', 'with, comma', 'with "quote"'];
        $line = implode(',', array_map(Csv::escapeString(...), $values));

        $this->assertSame($values, Csv::parseString($line));
    }

    #[DataProvider('endQuoteProvider')]
    public function testHasEndQuote(string $token, bool $expected): void
    {
        $this->assertSame($expected, Csv::_hasEndQuote($token));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function endQuoteProvider(): iterable
    {
        yield 'empty' => ['', false];
        yield 'single quote' => ['"', true];
        yield 'text with end quote' => ['abc"', true];
        yield 'escaped end quote' => ['abc""', false];
        yield 'escaped quote plus end quote' => ['abc"""', true];
        yield 'only escaped quotes' => ['""', false];
        yield 'no quote' => ['abc', false];
    }
}
