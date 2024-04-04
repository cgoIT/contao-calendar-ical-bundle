<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import\Csv;

class Csv
{
    /**
     * take a CSV line (utf-8 encoded) and returns an array
     * 'string1,string2,"string3","the ""string4"""' => array('string1', 'string2',
     * 'string3', 'the "string4"').
     *
     * @return array<string>
     */
    public static function parseString(string $string, string $separator = ','): array
    {
        $values = [];
        $string = str_replace("\r\n", '', $string); // eat the traling new line, if any

        if ('' === $string) {
            return $values;
        }

        $tokens = explode($separator, $string);
        $count = \count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            $len = \strlen($token);
            $newValue = '';
            if ($len > 0 && '"' === $token[0]) { // if quoted
                $token = substr($token, 1); // remove leading quote

                do { // concatenate with next token while incomplete
                    $complete = self::_hasEndQuote($token);
                    $token = str_replace('""', '"', $token); // unescape escaped quotes
                    $len = \strlen($token);
                    if ($complete) { // if complete
                        $newValue .= substr($token, 0, -1); // remove trailing quote
                    } else { // incomplete, get one more token
                        $newValue .= $token;
                        $newValue .= $separator;
                        if ($i === $count - 1) {
                            throw new \Exception('Illegal unescaped quote.'); // @phpstan-ignore-line
                        }
                        $token = $tokens[++$i];
                    }
                } while (!$complete);
            } else { // unescaped, use token as is
                $newValue .= $token;
            }

            $values[] = $newValue;
        }

        return $values;
    }

    public static function escapeString(string $string): string
    {
        $string = str_replace('"', '""', $string);

        if (
            str_contains($string, '"') || str_contains($string, ',') || str_contains($string,
                "\r") || str_contains($string, "\n")
        ) {
            $string = '"'.$string.'"';
        }

        return $string;
    }

    // checks if a string ends with an unescaped quote 'string"' => true 'string""'
    // => false 'string"""' => true
    public static function _hasEndQuote(string $token): bool
    {
        $len = \strlen($token);

        if (0 === $len) {
            return false;
        }
        if (1 === $len && '"' === $token) {
            return true;
        }
        if ($len > 1) {
            while ($len > 1 && '"' === $token[$len - 1] && '"' === $token[$len - 2]) { // there is an escaped quote at the end
                $len -= 2; // strip the escaped quote at the end
            }

            if (0 === $len) {
                return false;
            } // the string was only some escaped quotes
            if ('"' === $token[$len - 1]) {
                return true;
            } // the last quote was not escaped
        }

        return false;
        // was not ending with an unescaped quote
    }
}
