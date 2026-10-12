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

use Contao\ArticleModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
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
    public function isElementVisible(ContentModel|ModuleModel $element, int $depth = 0): bool
    {
        if ($element instanceof ContentModel && !$this->isPublished($element)) {
            return false;
        }

        if (!$this->isGrantedFor($element)) {
            return false;
        }

        // A content element is only as visible as the article/element/page it belongs to
        return !$element instanceof ContentModel || $this->isParentVisible($element, $depth);
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

    /**
     * Checks "guests only" and the member group protection of a record (module, content element, article, page).
     */
    private function isGrantedFor(ContentModel|ModuleModel|ArticleModel|PageModel $record): bool
    {
        if ($record->guests && $this->security->isGranted('ROLE_MEMBER')) {
            return false;
        }

        if ($record->protected) {
            return $this->security->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, StringUtil::deserialize($record->groups, true));
        }

        return true;
    }

    private function isParentVisible(ContentModel $element, int $depth): bool
    {
        // Content elements can be nested; the limit protects against cycles
        if ('tl_content' === $element->ptable) {
            $parent = $depth < 10 ? ContentModel::findById($element->pid) : null;

            return null !== $parent && $this->isElementVisible($parent, $depth + 1);
        }

        // Elements of other tables (e.g. news, events) are not part of an article
        if (!\in_array((string) $element->ptable, ['', 'tl_article'], true)) {
            return true;
        }

        $article = ArticleModel::findById($element->pid);

        if (null === $article || !$this->isArticlePublished($article) || !$this->isGrantedFor($article)) {
            return false;
        }

        $page = $this->loadPage((int) $article->pid);

        return null !== $page && $this->isPagePublished($page) && $this->isGrantedFor($page);
    }

    /**
     * Loads the page including the settings that are inherited from the parent pages.
     */
    protected function loadPage(int $id): PageModel|null
    {
        return PageModel::findWithDetails($id);
    }

    private function isArticlePublished(ArticleModel $article): bool
    {
        return $this->isInPublishedPeriod((bool) $article->published, $article->start, $article->stop);
    }

    private function isPagePublished(PageModel $page): bool
    {
        return $this->isInPublishedPeriod((bool) $page->published, $page->start, $page->stop);
    }

    private function isInPublishedPeriod(bool $published, mixed $start, mixed $stop): bool
    {
        $time = Date::floorToMinute();

        return $published
            && (empty($start) || (int) $start <= $time)
            && (empty($stop) || (int) $stop > $time);
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
