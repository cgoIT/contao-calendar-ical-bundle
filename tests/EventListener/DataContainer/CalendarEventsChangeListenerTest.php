<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer\CalendarEventsChangeListener;
use Cgoit\ContaoCalendarIcalBundle\EventListener\SubscriptionRegenerationListener;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarEventsModel;
use Contao\DataContainer;
use Contao\Model\Registry;
use Psr\Log\NullLogger;

final class CalendarEventsChangeListenerTest extends ContaoTestCase
{
    private SubscriptionRegenerationListener $regeneration;

    private CalendarEventsChangeListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();

        $this->regeneration = new SubscriptionRegenerationListener($this->createStub(ExportController::class), new NullLogger());
        $this->listener = new CalendarEventsChangeListener($this->regeneration);
    }

    public function testSavedEventSchedulesItsCalendar(): void
    {
        $this->event(5, 7);

        $this->listener->onSubmit($this->dataContainer(5));

        $this->assertSame([7], $this->scheduledCalendars());
    }

    public function testCopiedEventSchedulesItsCalendar(): void
    {
        $this->event(6, 8);

        $this->listener->onCopy(6, $this->dataContainer(5));

        $this->assertSame([8], $this->scheduledCalendars());
    }

    public function testDeletedEventSchedulesItsCalendar(): void
    {
        $this->listener->onDelete($this->dataContainer(5, ['id' => 5, 'pid' => 9]), 1);

        $this->assertSame([9], $this->scheduledCalendars());
    }

    public function testRestoredEventSchedulesItsCalendar(): void
    {
        $this->listener->onUndo('tl_calendar_events', ['id' => 5, 'pid' => 4], $this->dataContainer(5));
        $this->listener->onUndo('tl_calendar', ['id' => 5, 'pid' => 3], $this->dataContainer(5));

        $this->assertSame([4], $this->scheduledCalendars());
    }

    public function testMovedEventSchedulesAllCalendars(): void
    {
        $this->listener->onCut($this->dataContainer(5));

        $this->assertTrue($this->scheduledAll());
    }

    public function testUnknownEventIsIgnored(): void
    {
        $this->listener->onSubmit($this->dataContainer(0));
        $this->listener->onDelete($this->dataContainer(5, ['id' => 5]), 1);

        $this->assertSame([], $this->scheduledCalendars());
        $this->assertFalse($this->scheduledAll());
    }

    private function event(int $id, int $pid): void
    {
        $event = new CalendarEventsModel();
        $event->id = $id;
        $event->pid = $pid;
        Registry::getInstance()->register($event);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function dataContainer(int $id, array $record = []): DataContainer
    {
        $dc = $this->createStub(DataContainer::class);
        $dc
            ->method('__get')
            ->willReturnCallback(static fn (string $key): int|null => 'id' === $key ? $id : null)
        ;

        $dc
            ->method('getCurrentRecord')
            ->willReturn($record)
        ;

        return $dc;
    }

    /**
     * @return array<int>
     */
    private function scheduledCalendars(): array
    {
        return array_keys((new \ReflectionProperty($this->regeneration, 'calendarIds'))->getValue($this->regeneration));
    }

    private function scheduledAll(): bool
    {
        return (bool) (new \ReflectionProperty($this->regeneration, 'all'))->getValue($this->regeneration);
    }
}
