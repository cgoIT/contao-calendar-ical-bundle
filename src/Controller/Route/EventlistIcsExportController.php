<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\Route;

use Cgoit\ContaoCalendarIcalBundle\Export\EventlistModule;
use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Security\ExportAccessChecker;
use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use Contao\CalendarBundle\Generator\CalendarEventsGenerator;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_eventlist/ics-export/{id}',
    name: 'eventlist_frontend_ics_export',
    defaults: ['_scope' => 'frontend'])
]
class EventlistIcsExportController
{
    public function __construct(
        private readonly ResponseUtil $responseUtil,
        private readonly IcsExport $icsExport,
        private readonly ContaoFramework $contaoFramework,
        private readonly ExportAccessChecker $accessChecker,
        private readonly CalendarEventsGenerator $eventsGenerator,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        if (!is_numeric($id)) {
            throw new BadRequestHttpException('Parameter "id" has to be numeric');
        }

        return $this->exportEventlist($request, (int) $id);
    }

    /**
     * @throws \Exception
     */
    private function exportEventlist(Request $request, int $eventlistId): Response
    {
        $this->contaoFramework->initialize();
        System::loadLanguageFile('default');
        System::loadLanguageFile('tl_calendar');
        System::loadLanguageFile('tl_calendar_events');

        // The id could be of a module or a content element. Only eventlists the current visitor
        // is allowed to see can be exported.
        $objEventlist = ModuleModel::findById($eventlistId);
        if (null === $objEventlist || !$this->isEventlist($objEventlist)) {
            $objEventlist = ContentModel::findById($eventlistId);

            if (null === $objEventlist || !$this->isEventlist($objEventlist)) {
                throw new NotFoundHttpException('Eventlist not found');
            }
        }

        if (!$this->accessChecker->isElementVisible($objEventlist)) {
            throw new NotFoundHttpException('Eventlist not found');
        }

        $arrEvents = $this->getEvents($request, $objEventlist);
        if (empty($arrEvents)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $iCal = $this->icsExport->exportEvents($this->flatten($arrEvents))->createCalendar();
        $filename = StringUtil::sanitizeFileName($objEventlist->name ?? $eventlistId).'.ics';

        $response = $this->responseUtil->createDownloadResponse($iCal, $filename);

        // The result depends on the visitor (access protection of the element, the article, the page and the calendars)
        $response->setPrivate();

        $calendars = CalendarModel::findMultipleByIds(StringUtil::deserialize($objEventlist->cal_calendar, true));
        if ($objEventlist->protected || $this->accessChecker->hasProtectedCalendar($calendars)) {
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }

    private function isEventlist(ContentModel|ModuleModel $element): bool
    {
        return str_starts_with((string) $element->type, 'eventlist') && !empty($element->cal_calendar);
    }

    /**
     * @return array<mixed>
     */
    private function getEvents(Request $request, ContentModel|ModuleModel $objEventlist): array
    {
        $calendarIds = array_map(
            static fn (CalendarModel $calendar): int => (int) $calendar->id,
            $this->accessChecker->filterCalendars(CalendarModel::findMultipleByIds(StringUtil::deserialize($objEventlist->cal_calendar, true))),
        );

        if ([] === $calendarIds) {
            return [];
        }

        $format = (string) $objEventlist->cal_format;
        $year = (int) $request->query->get('year');
        $month = (int) $request->query->get('month');
        $day = (int) $request->query->get('day');

        // Handle featured events
        $featured = match ($objEventlist->cal_featured) {
            'featured' => true,
            'unfeatured' => false,
            default => null,
        };

        // Jump to the current period
        if (!$request->query->has('year') && !$request->query->has('month') && !$request->query->has('day')) {
            switch ($format) {
                case 'cal_year':
                    $year = (int) date('Y');
                    break;

                case 'cal_month':
                    $month = (int) date('Ym');
                    break;

                case 'cal_day':
                    $day = (int) date('Ymd');
                    break;
            }
        }

        $dynamicFormat = !$objEventlist->cal_ignoreDynamic && \in_array($format, ['cal_day', 'cal_month', 'cal_year'], true);

        // Create the date object
        $date = new Date();

        try {
            if ($dynamicFormat && $year) {
                $date = new Date($year, 'Y');
                $format = 'cal_year';
            } elseif ($dynamicFormat && $month) {
                $date = new Date($month, 'Ym');
                $format = 'cal_month';
            } elseif ($dynamicFormat && $day) {
                $date = new Date($day, 'Ymd');
                $format = 'cal_day';
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.$request->getUri());
        }

        $row = $objEventlist->row();
        $row['cal_calendar'] = $calendarIds;
        $row['cal_format'] = $format;
        $module = new EventlistModule($row);

        [$start, $end] = $module->getPeriod($date, $format);

        return $this->withPageContext($request, fn (): array => $this->eventsGenerator->getAllEvents(
            $calendarIds,
            (new \DateTime())->setTimestamp($start),
            (new \DateTime())->setTimestamp($end),
            $featured,
            (bool) $module->cal_noSpan,
            null,
            $module,
        ));
    }

    /**
     * The events generator (and the hooks) rely on the current page, which is not set on this route.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withPageContext(Request $request, callable $callback): mixed
    {
        $originalGlobalPage = $GLOBALS['objPage'] ?? null;
        $originalRequestPage = $request->attributes->get('pageModel');

        $page = $originalGlobalPage ?: PageModel::findPublishedFallbackByHostname($request->getHost(), ['fallbackToEmpty' => true]);

        $GLOBALS['objPage'] = $page;

        if (null === $originalRequestPage) {
            $request->attributes->set('pageModel', $page);
        }

        try {
            return $callback();
        } finally {
            $GLOBALS['objPage'] = $originalGlobalPage;
            $request->attributes->set('pageModel', $originalRequestPage);
        }
    }

    /**
     * @param array<mixed> $arrEvents
     *
     * @return array<mixed>
     */
    private function flatten(array $arrEvents): array
    {
        $result = [];

        foreach ($arrEvents as $day) {
            if (\is_array($day)) {
                foreach ($day as $timestamp) {
                    if (\is_array($timestamp)) {
                        $result = array_merge($result, $timestamp);
                    }
                }
            }
        }

        return $result;
    }
}
