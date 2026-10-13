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

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Slug\Slug;
use Contao\Model\Collection;
use Contao\StringUtil;

class AbstractImport
{
    public function __construct(protected readonly Slug $slug)
    {
    }

    /**
     * Whether the events that are imported into the calendar are published. Calendars that
     * were created before the setting existed publish them.
     */
    protected function publishImportedEvents(CalendarModel|null $calendar): bool
    {
        if (null === $calendar || null === $calendar->ical_publish_imported) {
            return true;
        }

        return (bool) $calendar->ical_publish_imported;
    }

    /**
     * The state of an imported event: published if the calendar publishes imported events, otherwise new events are
     * unpublished and existing ones keep their state (null).
     */
    protected function getPublishedState(bool $publishImported, bool $isNew): bool|null
    {
        if ($publishImported) {
            return true;
        }

        return $isNew ? false : null;
    }

    /**
     * Encodes text from an external source (ics/csv) before it is stored. Contao stores form input
     * encoded as well, so the value can be output in templates as usual. Insert tags are removed
     * to prevent that foreign content triggers them.
     */
    protected function encodeImportedText(string $text): string
    {
        return StringUtil::specialchars($text, true);
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
            // Each text gets its own element; a reused model would overwrite the previous text
            $cm = $contentDictionary[$step] ?? new ContentModel();
            unset($contentDictionary[$step]);
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
