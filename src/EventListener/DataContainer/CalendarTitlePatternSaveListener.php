<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * The pattern is used with preg_replace() on every imported title, so it has to be a valid regular expression.
 */
#[AsCallback(table: 'tl_calendar', target: 'fields.ical_pattern_event_title.save')]
class CalendarTitlePatternSaveListener
{
    public function __invoke(mixed $value, DataContainer|null $dc = null): mixed
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        // Compile the expression; PHP reports the reason for an invalid pattern as a warning
        $error = null;
        set_error_handler(static function (int $errno, string $message) use (&$error): bool {
            $error = preg_replace('/^preg_match\(\): /', '', $message);

            return true;
        });

        try {
            $valid = false !== preg_match((string) $value, '');
        } finally {
            restore_error_handler();
        }

        if (!$valid) {
            throw new \RuntimeException(\sprintf($GLOBALS['TL_LANG']['ERR']['ical_invalid_pattern'], $error ?? preg_last_error_msg()));
        }

        return $value;
    }
}
