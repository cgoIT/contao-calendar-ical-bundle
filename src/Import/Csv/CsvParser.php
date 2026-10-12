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

class CsvParser
{
    protected string $separator;

    protected CsvReader $reader;

    public function __construct(
        protected string $filename,
        protected string $encoding = 'utf8',
    ) {
        $this->separator = $this->determineSeparator();
        $this->reader = new CsvReader($filename, $this->separator, $this->encoding);
    }

    /**
     * @return array<mixed>
     */
    public function extractHeader(): array
    {
        $this->reader->rewind();

        return $this->reader->current() ?? [];
    }

    /**
     * @return array<mixed>|bool
     */
    public function getDataArray(int $lines = 1): array|bool
    {
        if (1 === $lines) {
            $this->reader->next();
            if ($this->reader->valid()) {
                return $this->reader->current() ?? [];
            }

            return false;
        }

        $res = [];

        do {
            $this->reader->next();
            $val = $this->reader->current();
            if (!empty($val)) {
                $res[] = $val;
            }
            --$lines;
        } while ($this->reader->valid() && $lines > 0);

        if (\count($res)) {
            return $res;
        }

        return false;
    }

    /**
     * The separator is the one ("," or ";") that occurs more often outside of quotes in the header line.
     */
    protected function determineSeparator(): string
    {
        $file = @fopen($this->filename, 'r');

        if (false === $file) {
            return ',';
        }

        $line = (string) fgets($file);
        fclose($file);

        $counts = [',' => 0, ';' => 0];
        $quoted = false;

        foreach (str_split($line) as $char) {
            if ('"' === $char) {
                $quoted = !$quoted;
            } elseif (!$quoted && isset($counts[$char])) {
                ++$counts[$char];
            }
        }

        return $counts[';'] > $counts[','] ? ';' : ',';
    }
}
