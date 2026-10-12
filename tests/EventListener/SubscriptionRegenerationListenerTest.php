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

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Cgoit\ContaoCalendarIcalBundle\EventListener\SubscriptionRegenerationListener;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarModel;
use Contao\Model\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SubscriptionRegenerationListenerTest extends ContaoTestCase
{
    private ExportController&MockObject $export;

    private SubscriptionRegenerationListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();
        $this->export = $this->createMock(ExportController::class);
        $this->listener = new SubscriptionRegenerationListener($this->export, new NullLogger());
    }

    public function testDoesNothingWithoutScheduledCalendars(): void
    {
        $this->export
            ->expects($this->never())
            ->method('generateSubscriptions')
        ;

        $this->terminate();
    }

    public function testRebuildsEveryScheduledCalendarOnce(): void
    {
        $first = $this->calendar(1);
        $second = $this->calendar(2);

        $rebuilt = [];
        $this->export
            ->expects($this->exactly(2))
            ->method('generateSubscriptions')
            ->willReturnCallback(
                static function (CalendarModel $calendar) use (&$rebuilt): void {
                    $rebuilt[] = (int) $calendar->id;
                },
            )
        ;

        $this->listener->schedule(1);
        $this->listener->schedule(2);
        $this->listener->schedule(1);

        $this->terminate();

        $this->assertSame([1, 2], $rebuilt);
        $this->assertSame(1, $first->refreshed);
        $this->assertSame(1, $second->refreshed);
    }

    public function testRebuildsOnlyOnceForTheNextRequest(): void
    {
        $this->calendar(1);
        $this->export
            ->expects($this->once())
            ->method('generateSubscriptions')
        ;

        $this->listener->schedule(1);
        $this->terminate();
        $this->terminate();
    }

    public function testRebuildsAllCalendarsAtOnce(): void
    {
        $this->calendar(1);
        $this->export
            ->expects($this->once())
            ->method('generateSubscriptions')
            ->with(null)
        ;

        $this->listener->schedule(1);
        $this->listener->scheduleAll();

        $this->terminate();
    }

    public function testErrorDoesNotBreakTheResponse(): void
    {
        $this->calendar(1);
        $this->export
            ->method('generateSubscriptions')
            ->willThrowException(new \RuntimeException('disk full'))
        ;

        $this->listener->schedule(1);
        $this->terminate();

        $this->addToAssertionCount(1);
    }

    private function terminate(): void
    {
        $this->listener->onTerminate(new TerminateEvent($this->createStub(HttpKernelInterface::class), new Request(), new Response()));
    }

    private function calendar(int $id): CalendarModel
    {
        $calendar = new class() extends CalendarModel {
            public int $refreshed = 0;

            public function refresh(): static
            {
                ++$this->refreshed;

                return $this;
            }
        };
        $calendar->id = $id;
        Registry::getInstance()->register($calendar);

        return $calendar;
    }
}
