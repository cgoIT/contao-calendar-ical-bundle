<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import\Csv;

/**
 * @implements \Iterator<int, array>
 */
class CsvReader implements \Iterator
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * @var resource|null
     */
    protected mixed $fileHandle = null;

    protected int $position = 0;

    protected string $filename = '';

    /**
     * @var array<mixed>|null
     */
    protected array|null $currentArray = null;

    public function __construct(
        string $filename,
        protected string $separator = ',',
        protected string $encoding = 'utf8',
    ) {
        $handle = @fopen($filename, 'r');

        if (false === $handle) {
            return;
        }

        $this->fileHandle = $handle;
        $this->filename = $filename;
        $this->start();
    }

    public function __destruct()
    {
        $this->close();
    }

    // You should not have to call it unless you need to explicitly free the
    // file descriptor
    public function close(): void
    {
        if (null !== $this->fileHandle) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    public function rewind(): void
    {
        $this->start();
    }

    /**
     * @return array<mixed>|null
     */
    public function current(): array|null
    {
        return $this->currentArray;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
        $this->readRecord();
    }

    public function valid(): bool
    {
        return null !== $this->currentArray;
    }

    private function start(): void
    {
        $this->position = 0;

        if (null === $this->fileHandle) {
            $this->currentArray = null;

            return;
        }

        rewind($this->fileHandle);

        // Excel and others write a byte order mark in front of UTF-8 files
        if (self::UTF8_BOM !== fread($this->fileHandle, 3)) {
            rewind($this->fileHandle);
        }

        $this->readRecord();
    }

    /**
     * Reads the next record. Empty lines are skipped, quoted fields may contain line breaks.
     */
    private function readRecord(): void
    {
        $this->currentArray = null;

        if (null === $this->fileHandle) {
            return;
        }

        while (false !== ($row = fgetcsv($this->fileHandle, 0, $this->separator, '"', ''))) {
            // fgetcsv() returns array(null) for an empty line
            if ([null] === $row) {
                continue;
            }

            $this->currentArray = array_map(fn (string|null $cell): string => $this->toUtf8((string) $cell), $row);

            return;
        }
    }

    private function toUtf8(string $value): string
    {
        switch (strtolower($this->encoding)) {
            case 'utf8':
            case 'utf-8':
                return $value;

            // The option "ISO-8859-1 (Windows)" means files exported by Windows programs, i.e. Windows-1252
            case 'latin1':
            case 'iso-8859-1':
            case 'iso8859-1':
            case 'windows-1252':
            case 'cp1252':
                return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }

        foreach (mb_list_encodings() as $known) {
            if (0 === strcasecmp($known, $this->encoding)) {
                return mb_convert_encoding($value, 'UTF-8', $known);
            }
        }

        return $value;
    }
}
