<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Util;

use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class ResponseUtilTest extends TestCase
{
    public function testCreatesDownloadResponse(): void
    {
        $response = (new ResponseUtil())->createDownloadResponse('BEGIN:VCALENDAR', 'calendar.ics');

        $this->assertSame('BEGIN:VCALENDAR', $response->getContent());
        $this->assertSame('text/calendar', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=calendar.ics', $response->headers->get('Content-Disposition'));
    }

    public function testSupportsInlineDisposition(): void
    {
        $response = (new ResponseUtil())->createDownloadResponse('x', 'calendar.ics', 'text/plain', ResponseHeaderBag::DISPOSITION_INLINE);

        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function testUsesAsciiFallbackForUmlautsInFileName(): void
    {
        $response = (new ResponseUtil())->createDownloadResponse('x', 'Termine Köln.ics');
        $disposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringContainsString('filename="Termine K_ln.ics"', $disposition);
        $this->assertStringContainsString("filename*=utf-8''Termine%20K%C3%B6ln.ics", $disposition);
    }

    public function testWrapsDownloadResponseInException(): void
    {
        $exception = (new ResponseUtil())->sendFileForDownload('x', 'calendar.ics');

        $this->assertSame('x', $exception->getResponse()->getContent());
        $this->assertSame('text/calendar', $exception->getResponse()->headers->get('Content-Type'));
    }
}
