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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ResponseUtil
{
    public function returnMemoryFile(string $fileContent, string $fileName, string $contentType = 'text/calendar', string $contentDisposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): Response
    {
        $response = new Response();

        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', $contentType.'; charset=utf-8');
        $response->headers->set('Content-Disposition', $contentDisposition.'; filename="'.$fileName.'";');
        $response->headers->set('Content-length', ''.\strlen($fileContent));
        //        $response->sendHeaders();

        $response->setContent($fileContent);

        return $response;
    }
}
