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

use Cgoit\ContaoCalendarIcalBundle\EventListener\SubscriptionRegenerationListener;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * Rebuilds the subscription file of a calendar when one of its events changes.
 */
class CalendarEventsChangeListener
{
    public function __construct(private readonly SubscriptionRegenerationListener $regeneration)
    {
    }

    /**
     * Saved or published/unpublished.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function onSubmit(DataContainer $dc): void
    {
        $this->scheduleEvent((int) $dc->id);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncopy')]
    public function onCopy(int|string $insertId, DataContainer $dc): void
    {
        $this->scheduleEvent((int) $insertId);
    }

    /**
     * The event might have been moved to another calendar, the old one is not known anymore.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncut')]
    public function onCut(DataContainer $dc): void
    {
        $this->regeneration->scheduleAll();
    }

    /**
     * Called before the record is deleted, so the file is rebuilt after the request.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.ondelete')]
    public function onDelete(DataContainer $dc, int $undoId): void
    {
        $record = $dc->getCurrentRecord();

        if (!empty($record['pid'])) {
            $this->regeneration->schedule((int) $record['pid']);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onundo')]
    public function onUndo(string $table, array $row, DataContainer $dc): void
    {
        if ('tl_calendar_events' === $table && !empty($row['pid'])) {
            $this->regeneration->schedule((int) $row['pid']);
        }
    }

    private function scheduleEvent(int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }

        $event = CalendarEventsModel::findById($eventId);

        if (null !== $event && !empty($event->pid)) {
            $this->regeneration->schedule((int) $event->pid);
        }
    }
}
