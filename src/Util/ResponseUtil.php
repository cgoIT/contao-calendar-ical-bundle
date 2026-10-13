<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
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
    public function createDownloadResponse(string $fileContent, string $fileName, string $contentType = 'text/calendar', string $contentDisposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): Response
    {
        $icalResponse = new Response($fileContent);
        $icalResponse->headers->set('Content-Type', $contentType);
        $icalResponse->headers->set('Content-Disposition',
            HeaderUtils::makeDisposition($contentDisposition, $fileName, $this->getFilenameFallback($fileName)),
        );

        return $icalResponse;
    }

    /**
     * For fragment controllers (e.g. content elements), whose response would otherwise be embedded in the page.
     */
    public function sendFileForDownload(string $fileContent, string $fileName, string $contentType = 'text/calendar', string $contentDisposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): ResponseException
    {
        return new ResponseException($this->createDownloadResponse($fileContent, $fileName, $contentType, $contentDisposition));
    }

    /**
     * The header needs an ASCII fallback for file names with special characters; every one is replaced by "_".
     */
    private function getFilenameFallback(string $filename): string
    {
        return preg_replace('/[^\x20-\x7e]|%/u', '_', $filename)
            ?? preg_replace('/[^\x20-\x7e]|%/', '_', $filename)
            ?? '_';
    }
}
