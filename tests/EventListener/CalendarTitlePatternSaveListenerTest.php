<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\EventListener;

use Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer\CalendarTitlePatternSaveListener;
use PHPUnit\Framework\TestCase;

final class CalendarTitlePatternSaveListenerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TL_LANG']['ERR']['ical_invalid_pattern'] = 'invalid: %s';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_LANG']['ERR']['ical_invalid_pattern']);
    }

    public function testAcceptsEmptyValueAndValidPattern(): void
    {
        $listener = new CalendarTitlePatternSaveListener();

        $this->assertSame('', $listener(''));
        $this->assertNull($listener(null));
        $this->assertSame('/^\[Club\] /i', $listener('/^\[Club\] /i'));
    }

    public function testRejectsPatternWithoutDelimiters(): void
    {
        $this->expectException(\RuntimeException::class);

        (new CalendarTitlePatternSaveListener())('Club');
    }

    public function testErrorMessageContainsReasonOfPhp(): void
    {
        try {
            (new CalendarTitlePatternSaveListener())('/(unclosed/');
            $this->fail('Exception expected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('missing closing parenthesis', $e->getMessage());
        }
    }

    public function testRejectsInvalidPattern(): void
    {
        $this->expectException(\RuntimeException::class);

        (new CalendarTitlePatternSaveListener())('/(unclosed/');
    }
}
