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
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_event/ics-export/{id}', name: 'event_frontend_ics_export', defaults: ['_scope' => 'frontend'])]
class SingleEventIcsExportController extends AbstractController
{
    public function __construct(
        private readonly ResponseUtil $responseUtil,
        private readonly IcsExport $icsExport,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $this->initializeContaoFramework();

        if (!is_numeric($id)) {
            throw new BadRequestHttpException('Parameter "id" has to be numeric');
        }

        return $this->exportEvent((int) $id);
    }

    /**
     * @throws \Exception
     */
    private function exportEvent(int $eventId): Response
    {
        $objEvent = CalendarEventsModel::findById($eventId);
        if (empty($objEvent)) {
            throw new NotFoundHttpException('Event not found');
        }

        $icsEvent = $this->icsExport->exportEvent($objEvent)->createCalendar();
        $filename = StringUtil::sanitizeFileName($objEvent->title ?? $eventId).'.ics';

        $this->responseUtil->sendFileForDownload($icsEvent, $filename);

        // this statement is never reached
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
