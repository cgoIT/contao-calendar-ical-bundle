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

/**
 * The backend import passes the path of the uploaded file on via a form field. This class makes sure
 * the path only points to an ics file in the upload directory "system/tmp".
 */
class UploadedIcsFileResolver
{
    private const UPLOAD_DIR = 'system/tmp';

    public function __construct(private readonly string $projectDir)
    {
    }

    /**
     * @return string|null The path relative to the project directory or null if the path is not allowed
     */
    public function resolve(string $path): string|null
    {
        $projectDir = realpath($this->projectDir);
        $uploadDir = realpath($this->projectDir.'/'.self::UPLOAD_DIR);
        $realPath = realpath($this->projectDir.'/'.ltrim($path, '/'));

        if (false === $projectDir || false === $uploadDir || false === $realPath || !is_file($realPath)) {
            return null;
        }

        if (!str_starts_with($realPath, $uploadDir.\DIRECTORY_SEPARATOR) || 'ics' !== strtolower(pathinfo($realPath, PATHINFO_EXTENSION))) {
            return null;
        }

        return ltrim(substr($realPath, \strlen($projectDir)), \DIRECTORY_SEPARATOR);
    }
}
