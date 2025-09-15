<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\Route;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @noRector \Rector\PhpAttribute\Rector\Class_\RenameAttributeRector
 */
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
    ) {
    }

    public function __get($strKey): mixed
    {
        return $this->arrData[$strKey] ?? parent::__get($strKey);
    }

    public function __invoke(string $id): Response
    {
        System::getContainer()->get('contao.framework')->initialize();

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
        // The id could be of an Module or a ContentElement
        $objEventlist = ModuleModel::findById($eventlistId);
        if (empty($objEventlist)) {
            $objEventlist = ContentModel::findById($eventlistId);

            if (empty($objEventlist)) {
                throw new NotFoundHttpException('Eventlist not found');
            }
        }

        $this->arrData = $objEventlist->row();

        $arrEvents = $this->getEvents($objEventlist);
        if (empty($arrEvents)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $arrEvents = $this->flatten($arrEvents);

        $iCal = $this->icsExport->exportEvents($arrEvents)->createCalendar();
        $filename = StringUtil::sanitizeFileName($objEventlist->name ?? $eventlistId).'.ics';

        throw $this->responseUtil->sendFileForDownload($iCal, $filename);
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
        return $this->getAllEvents($cal_calendar, $intStart, $intEnd, $blnFeatured);
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
