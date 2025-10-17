<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Util;

use Contao\CoreBundle\Exception\ResponseException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ResponseUtil
{
    public function sendFileForDownload(string $fileContent, string $fileName, string $contentType = 'text/calendar', string $contentDisposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): ResponseException
    {
        $icalResponse = new Response($fileContent);
        $icalResponse->headers->set('Content-Type', $contentType);
        $icalResponse->headers->set('Content-Disposition',
            HeaderUtils::makeDisposition($contentDisposition, $fileName, $this->getFilenameFallback($fileName)),
        );

        return new ResponseException($icalResponse);
    }

    private function getFilenameFallback(string $filename): string
    {
        if (!preg_match('/^[\x20-\x7e]*$/', $filename) || str_contains($filename, '%')) {
            $filenameFallback = '';
            $encoding = mb_detect_encoding($filename, null, true) ?: '8bit';

            for ($i = 0, $filenameLength = mb_strlen($filename, $encoding); $i < $filenameLength; ++$i) {
                $char = mb_substr($filename, $i, 1, $encoding);

                if ('%' === $char || \ord($char) < 32 || \ord($char) > 126) {
                    $filenameFallback .= '_';
                } else {
                    $filenameFallback .= $char;
                }
            }

            return $filenameFallback;
        }

        return $filename;
    }
}
