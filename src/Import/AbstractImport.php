<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Import;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Slug\Slug;
use Contao\Model\Collection;

class AbstractImport extends Backend
{
    public function __construct(protected readonly Slug $slug)
    {
    }

    /**
     * @param array<mixed> $eventcontent
     */
    protected function addEventContent(CalendarEventsModel $objEvent, array $eventcontent): void
    {
        $step = 128;

        $columns = ['ptable=? AND pid=?'];
        $values = ['tl_calendar_events', $objEvent->id];
        $contents = ContentModel::findBy($columns, $values);
        $contentDictionary = [];

        if (!empty($contents)) {
            foreach ($contents as $content) {
                $key = $content->sorting;
                if (isset($contentDictionary[$key])) {
                    $key = uniqid('', true);
                }
                $contentDictionary[$key] = $content;
            }
        }

        foreach ($eventcontent as $content) {
            if (isset($contentDictionary[$step])) {
                $cm = $contentDictionary[$step];
                unset($contentDictionary[$step]);
            }
            $cm ??= new ContentModel();
            $cm->tstamp = time();
            $cm->pid = $objEvent->id;
            $cm->ptable = 'tl_calendar_events';
            $cm->sorting = $step;
            $step *= 2;
            $cm->type = 'text';
            $cm->text = $content;
            $cm->save();
        }

        foreach ($contentDictionary as $content) {
            $content->delete();
        }
    }

    /**
     * Auto-generate the event alias if it has not been set yet.
     */
    protected function generateAlias(CalendarEventsModel $objEvent): CalendarEventsModel
    {
        $aliasExists = static function (string $alias) use ($objEvent): bool {
            $objEvents = CalendarEventsModel::findBy(
                ['alias=?', 'id!=?'],
                [$alias, $objEvent->id],
            );

            return null !== $objEvents && $objEvents->count() > 0;
        };

        // Generate the alias if there is none
        $objEvent->alias = $this->slug->generate(
            $objEvent->title,
            CalendarModel::findById($objEvent->pid)->jumpTo,
            $aliasExists,
        );

        return $objEvent->save();
    }

    /**
     * Delete calendar events and associated content.
     *
     * @param Collection<CalendarEventsModel>|array<CalendarEventsModel>|null $events The event(s) to be deleted
     */
    protected function deleteEvents(Collection|array|null $events): void
    {
        if (empty($events)) {
            return;
        }

        foreach ($events as $event) {
            $columns = ['ptable=? AND pid=?'];
            $values = ['tl_calendar_events', $event->id];
            $content = ContentModel::findBy($columns, $values);

            if ($content) {
                while ($content->next()) {
                    $content->delete();
                }
            }

            $event->delete();
        }
    }
}
