<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\Page;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Contao\CalendarModel;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsPage(type: IcsFeedController::TYPE, contentComposition: false, urlSuffix: IcsFeedController::URL_SUFFIX)]
class IcsFeedController extends AbstractController
{
    final public const TYPE = 'ics_feed';

    private const URL_SUFFIX = '.ics';

    private const MIME_TYPE = 'text/calendar';

    public function __construct(
        private readonly IcsExport $icsExport,
        private readonly int $defaultEndTimeDifference,
    ) {
    }

    public function __invoke(Request $request, PageModel $pageModel): Response
    {
        $this->initializeContaoFramework();

        $startDate = !empty($pageModel->ical_start) ? (int) $pageModel->ical_start : time();
        $endDate = !empty($pageModel->ical_end) ? (int) $pageModel->ical_end :
            time() + $this->defaultEndTimeDifference * 24 * 3600;

        $arrCalendars = CalendarModel::findMultipleByIds(StringUtil::deserialize($pageModel->ical_calendar, true));
        $vCalendar = $this->icsExport->getVcalendar($arrCalendars, $startDate, $endDate,
            $pageModel->ical_title, $pageModel->ical_description, $pageModel->ical_prefix);

        $this->tagResponse($arrCalendars);

        $response = new Response($vCalendar->createCalendar());
        $response->headers->set('Content-Type', self::MIME_TYPE);

        $this->setCacheHeaders($response, $pageModel);

        return $response;
    }
}
