<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import\Csv;

/**
 * @implements \Iterator<int, array>
 */
class CsvReader implements \Iterator
{
    protected mixed $fileHandle;

    protected int $position;

    protected string $filename;

    protected string|null $currentLine = null;

    /** @var array<mixed>|null */
    protected array|null $currentArray = null;

    public function __construct(
        string $filename,
        protected string $separator = ',',
        protected string $encoding = 'utf8',
    ) {
        $this->fileHandle = fopen($filename, 'r');
        if (!$this->fileHandle) {
            return;
        }
        $this->filename = $filename;
        $this->position = 0;
        $this->_readLine();
    }

    public function __destruct()
    {
        $this->close();
    }

    // You should not have to call it unless you need to explicitly free the
    // file descriptor
    public function close(): void
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    public function rewind(): void
    {
        if ($this->fileHandle) {
            $this->position = 0;
            rewind($this->fileHandle);
        }

        $this->_readLine();
    }

    /**
     * @return array<mixed>
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
        $this->_readLine();
    }

    public function valid(): bool
    {
        return null !== $this->currentArray;
    }

    protected function _readLine(): void
    {
        if (!feof($this->fileHandle)) {
            if (false !== $line = fgets($this->fileHandle)) {
                $this->currentLine = trim($line);
            } else {
                $this->currentLine = null;
            }
        } else {
            $this->currentLine = null;
        }
        if (0 !== strcmp($this->encoding, 'utf8') && null !== $this->currentLine) {
            $this->currentLine = mb_convert_encoding($this->currentLine, 'UTF-8', 'ISO-8859-1');
        }
        if (!empty($this->currentLine)) {
            $this->currentArray = Csv::parseString($this->currentLine, $this->separator);
        } else {
            $this->currentArray = null;
        }
    }
}
