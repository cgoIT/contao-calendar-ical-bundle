<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Security;

use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Decides which calendars may be exported to the current visitor. Protected calendars are only
 * exported to front end members of the allowed groups.
 */
class ExportAccessChecker
{
    public function __construct(private readonly Security $security)
    {
    }

    public function canExportCalendar(CalendarModel $calendar): bool
    {
        if (!$calendar->protected) {
            return true;
        }

        return $this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $calendar->groups);
    }

    /**
     * Checks whether a module or content element is visible to the current visitor (publish state,
     * "guests only" and member group protection).
     */
    public function isElementVisible(ContentModel|ModuleModel $element): bool
    {
        if ($element instanceof ContentModel && !$this->isPublished($element)) {
            return false;
        }

        if ($element->guests && $this->security->isGranted('ROLE_MEMBER')) {
            return false;
        }

        if ($element->protected) {
            return $this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($element->groups, true));
        }

        return true;
    }

    /**
     * @param Collection<CalendarModel>|array<CalendarModel>|null $calendars
     *
     * @return array<CalendarModel>
     */
    public function filterCalendars(Collection|array|null $calendars): array
    {
        if (null === $calendars) {
            return [];
        }

        if ($calendars instanceof Collection) {
            $calendars = $calendars->getModels();
        }

        return array_values(array_filter($calendars, $this->canExportCalendar(...)));
    }

    /**
     * @param Collection<CalendarModel>|array<CalendarModel>|null $calendars
     */
    public function hasProtectedCalendar(Collection|array|null $calendars): bool
    {
        if ($calendars instanceof Collection) {
            $calendars = $calendars->getModels();
        }

        foreach ($calendars ?? [] as $calendar) {
            if ($calendar->protected) {
                return true;
            }
        }

        return false;
    }

    private function isPublished(ContentModel $element): bool
    {
        if ($element->invisible) {
            return false;
        }

        $time = Date::floorToMinute();

        return (empty($element->start) || (int) $element->start <= $time)
            && (empty($element->stop) || (int) $element->stop > $time);
    }
}
