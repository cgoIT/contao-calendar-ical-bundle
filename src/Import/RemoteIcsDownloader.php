<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads a remote ics file. Only http(s) is allowed, the TLS certificate is verified, requests to
 * private networks are blocked (unless a proxy is configured) and time, redirects and size are limited.
 */
class RemoteIcsDownloader
{
    final public const MAX_SIZE = 10 * 1024 * 1024;

    private const TIMEOUT = 10;

    private const MAX_DURATION = 30;

    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return string|null The content of the file or null if it could not be downloaded
     */
    public function download(string $url, string|null $proxy = null, string|null $credentials = null, int|null $port = null): string|null
    {
        $url = $this->normalizeUrl(html_entity_decode($url));

        if (null === $url) {
            $this->logger->error('Could not download ics file: only http and https URLs are supported.');

            return null;
        }

        $options = [
            'timeout' => self::TIMEOUT,
            'max_duration' => self::MAX_DURATION,
            'max_redirects' => self::MAX_REDIRECTS,
            'headers' => ['Accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.1'],
            'on_progress' => static function (int $downloaded, int $total): void {
                if ($downloaded > self::MAX_SIZE || $total > self::MAX_SIZE) {
                    throw new \RuntimeException('The ics file is too large.');
                }
            },
        ];

        $client = $this->httpClient;

        if (!empty($proxy)) {
            $options['proxy'] = $this->buildProxyUrl($proxy, $credentials, $port);
        } else {
            // With a proxy the target is resolved by the proxy, so the check would only see the proxy
            $client = new NoPrivateNetworkHttpClient($client);
        }

        $logUrl = $this->stripUserInfo($url);

        try {
            $response = $client->request('GET', $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $this->logger->error('Could not download ics file from URL "'.$logUrl.'". Got response code: '.$statusCode);

                return null;
            }

            $content = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Could not download ics file from URL "'.$logUrl.'": '.$e->getMessage());

            return null;
        }

        if ('' === trim($content)) {
            $this->logger->warning('The downloaded ics file from URL "'.$logUrl.'" seems to be empty.');

            return null;
        }

        return $content;
    }

    private function normalizeUrl(string $url): string|null
    {
        $url = trim($url);

        // webcal is the common scheme for calendar subscriptions and means http(s)
        $url = preg_replace('#^webcals://#i', 'https://', $url) ?? $url;
        $url = preg_replace('#^webcal://#i', 'https://', $url) ?? $url;

        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    private function buildProxyUrl(string $proxy, string|null $credentials, int|null $port): string
    {
        $scheme = 'http://';

        if (preg_match('#^([a-z0-9]+://)(.*)$#i', $proxy, $matches)) {
            [, $scheme, $proxy] = $matches;
        }

        $auth = '';

        if (!empty($credentials)) {
            // "user:password"; special characters must not break the URL
            [$user, $password] = array_pad(explode(':', $credentials, 2), 2, null);
            $auth = rawurlencode($user).(null !== $password ? ':'.rawurlencode($password) : '').'@';
        }
        $portSuffix = !empty($port) && !str_contains($proxy, ':') ? ':'.$port : '';

        return $scheme.$auth.$proxy.$portSuffix;
    }

    private function stripUserInfo(string $url): string
    {
        return preg_replace('#//[^/@]*@#', '//', $url) ?? $url;
    }
}
