<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Import;

use Cgoit\ContaoCalendarIcalBundle\Import\RemoteIcsDownloader;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RemoteIcsDownloaderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    /**
     * @var list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private array $requests = [];

    public function testDownloadsContent(): void
    {
        $downloader = $this->downloader(new MockResponse('BEGIN:VCALENDAR'));

        $this->assertSame('BEGIN:VCALENDAR', $downloader->download('https://example.com/cal.ics'));
        $this->assertSame('https://example.com/cal.ics', $this->requests[0][1]);
    }

    public function testDecodesHtmlEntitiesInUrl(): void
    {
        $downloader = $this->downloader(new MockResponse('x'));
        $downloader->download('https://example.com/cal.ics?a=1&amp;b=2');

        $this->assertSame('https://example.com/cal.ics?a=1&b=2', $this->requests[0][1]);
    }

    public function testWebcalIsRequestedViaHttps(): void
    {
        $downloader = $this->downloader(new MockResponse('x'));
        $downloader->download('webcal://example.com/cal.ics');

        $this->assertSame('https://example.com/cal.ics', $this->requests[0][1]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideForbiddenUrls')]
    public function testRejectsOtherSchemes(string $url): void
    {
        $downloader = $this->downloader(new MockResponse('secret'));

        $this->assertNull($downloader->download($url));
        $this->assertSame([], $this->requests);
        $this->assertNotEmpty($this->errors);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForbiddenUrls(): iterable
    {
        yield 'file' => ['file:///etc/passwd'];
        yield 'gopher' => ['gopher://example.com/'];
        yield 'ftp' => ['ftp://example.com/cal.ics'];
        yield 'no scheme' => ['example.com/cal.ics'];
    }

    public function testBlocksPrivateNetworks(): void
    {
        $downloader = $this->downloader(new MockResponse('secret'));

        $this->assertNull($downloader->download('http://127.0.0.1/cal.ics'));
        $this->assertNull($downloader->download('http://169.254.169.254/latest/meta-data/'));
        $this->assertSame([], $this->requests);
    }

    public function testDoesNotFollowRedirectToPrivateNetwork(): void
    {
        $downloader = $this->downloader(
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location: http://127.0.0.1/secret.ics']]),
            new MockResponse('secret'),
        );

        $this->assertNull($downloader->download('https://example.com/cal.ics'));
        $this->assertCount(1, $this->requests);
    }

    public function testReturnsNullOnErrorStatus(): void
    {
        $downloader = $this->downloader(new MockResponse('nope', ['http_code' => 404]));

        $this->assertNull($downloader->download('https://example.com/cal.ics'));
        $this->assertStringContainsString('404', $this->errors[0]);
    }

    public function testReturnsNullOnEmptyContent(): void
    {
        $downloader = $this->downloader(new MockResponse("  \n"));

        $this->assertNull($downloader->download('https://example.com/cal.ics'));
    }

    public function testLimitsTimeAndSize(): void
    {
        $downloader = $this->downloader(new MockResponse('x'));
        $downloader->download('https://example.com/cal.ics');

        $options = $this->requests[0][2];
        $this->assertSame(10.0, (float) $options['timeout']);
        $this->assertSame(30.0, (float) $options['max_duration']);

        $this->expectException(\RuntimeException::class);
        $options['on_progress'](RemoteIcsDownloader::MAX_SIZE + 1, 0, []);
    }

    public function testProxyIsPassedWithCredentialsAndPort(): void
    {
        $downloader = $this->downloader(new MockResponse('x'));

        $this->assertSame('x', $downloader->download('http://10.0.0.5/cal.ics', 'proxy.local', 'user:pw', 3128));
        $this->assertSame('http://user:pw@proxy.local:3128', $this->requests[0][2]['proxy']);
    }

    public function testErrorLogDoesNotContainCredentialsOfUrl(): void
    {
        $downloader = $this->downloader(new MockResponse('', ['http_code' => 500]));
        $downloader->download('https://user:secret@example.com/cal.ics');

        $this->assertStringNotContainsString('secret', $this->errors[0]);
    }

    private function downloader(MockResponse ...$responses): RemoteIcsDownloader
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = [$method, $url, $options];

            return 1 === \count($responses) ? $responses[0] : array_shift($responses);
        });

        $logger = new class($this->errors) extends AbstractLogger {
            /**
             * @param list<string> $errors
             */
            public function __construct(private array &$errors)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->errors[] = (string) $message;
            }
        };

        return new RemoteIcsDownloader($client, $logger);
    }
}
