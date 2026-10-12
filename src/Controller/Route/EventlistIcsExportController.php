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

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Security\ExportAccessChecker;
use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_eventlist/ics-export/{id}',
    name: 'eventlist_frontend_ics_export',
    defaults: ['_scope' => 'frontend'])
]
class EventlistIcsExportController extends Events
{
    /**
     * @var array<mixed>
     */
    protected $arrData = [];

    public function __construct(
        private readonly ResponseUtil $responseUtil,
        private readonly IcsExport $icsExport,
        private readonly ContaoFramework $contaoFramework,
        private readonly RequestStack $requestStack,
        private readonly ExportAccessChecker $accessChecker,
    ) {
    }

    public function __get($strKey): mixed
    {
        return $this->arrData[$strKey] ?? parent::__get($strKey);
    }

    public function __invoke(string $id): Response
    {
        if (!is_numeric($id)) {
            throw new BadRequestHttpException('Parameter "id" has to be numeric');
        }

        return $this->exportEventlist((int) $id);
    }

    protected function compile(): void
    {
    }

    /**
     * @throws \Exception
     */
    private function exportEventlist(int $eventlistId): Response
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

        $this->arrData = $objEventlist->row();

        $arrEvents = $this->getEvents($objEventlist);
        if (empty($arrEvents)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $arrEvents = $this->flatten($arrEvents);

        $iCal = $this->icsExport->exportEvents($arrEvents)->createCalendar();
        $filename = StringUtil::sanitizeFileName($objEventlist->name ?? $eventlistId).'.ics';

        $exception = $this->responseUtil->sendFileForDownload($iCal, $filename);

        // The result depends on the visitor (access protection of the element, the article, the page and the calendars)
        $exception->getResponse()->setPrivate();

        $calendars = CalendarModel::findMultipleByIds(StringUtil::deserialize($objEventlist->cal_calendar, true));
        if ($objEventlist->protected || $this->accessChecker->hasProtectedCalendar($calendars)) {
            $exception->getResponse()->headers->addCacheControlDirective('no-store');
        }

        throw $exception;
    }

    private function isEventlist(ContentModel|ModuleModel $element): bool
    {
        return str_starts_with((string) $element->type, 'eventlist') && !empty($element->cal_calendar);
    }

    /**
     * @return array<mixed>
     */
    private function getEvents(ContentModel|ModuleModel $objEventlist): array
    {
        global $objPage;

        if (empty($objPage)) {
            $dns = Environment::get('host');
            $objPage = PageModel::findPublishedFallbackByHostname($dns, ['fallbackToEmpty' => true]);
        }

        $originalPageModelInRequest = $this->requestStack->getCurrentRequest()?->attributes->get('pageModel');
        if (null === $originalPageModelInRequest) {
            $this->requestStack->getCurrentRequest()?->attributes->set('pageModel', $objPage);
        }

        $cal_calendar = $this->sortOutProtected(StringUtil::deserialize($objEventlist->cal_calendar, true));
        if (empty($cal_calendar) || !\is_array($cal_calendar)) {
            return [];
        }

        $this->arrData['cal_calendar'] = $cal_calendar;

        $intYear = (int) Input::get('year');
        $intMonth = (int) Input::get('month');
        $intDay = (int) Input::get('day');

        // Handle featured events
        $blnFeatured = null;

        if ('featured' === $objEventlist->cal_featured) {
            $blnFeatured = true;
        } elseif ('unfeatured' === $objEventlist->cal_featured) {
            $blnFeatured = false;
        }

        // Jump to the current period
        if (null === Input::get('year') && null === Input::get('month') && null === Input::get('day')) {
            switch ($objEventlist->cal_format) {
                case 'cal_year':
                    $intYear = date('Y');
                    break;

                case 'cal_month':
                    $intMonth = date('Ym');
                    break;

                case 'cal_day':
                    $intDay = date('Ymd');
                    break;
            }
        }

        $blnDynamicFormat = !$objEventlist->cal_ignoreDynamic && \in_array($objEventlist->cal_format, ['cal_day', 'cal_month', 'cal_year'], true);

        // Create the date object
        $date = new Date();

        try {
            if ($blnDynamicFormat && $intYear) {
                $date = new Date($intYear, 'Y');
                $objEventlist->cal_format = 'cal_year';
            } elseif ($blnDynamicFormat && $intMonth) {
                $date = new Date($intMonth, 'Ym');
                $objEventlist->cal_format = 'cal_month';
            } elseif ($blnDynamicFormat && $intDay) {
                $date = new Date($intDay, 'Ymd');
                $objEventlist->cal_format = 'cal_day';
            }
        } catch (\OutOfBoundsException) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        [$intStart, $intEnd] = $this->getDatesFromFormat($date, $objEventlist->cal_format);

        // Get all events
        $arrEvents = $this->getAllEvents($cal_calendar, $intStart, $intEnd, $blnFeatured);

        // reset pageModel in request
        $this->requestStack->getCurrentRequest()?->attributes->set('pageModel', $originalPageModelInRequest);

        return $arrEvents;
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
