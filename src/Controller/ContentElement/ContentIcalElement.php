<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Security\ExportAccessChecker;
use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(type: ContentIcalElement::TYPE, category: 'files')]
class ContentIcalElement extends AbstractContentElementController
{
    final public const TYPE = 'ical';

    protected string $strTitle = '';

    protected Vcalendar $ical;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ResponseUtil $responseUtil,
        private readonly IcsExport $icsExport,
        private readonly ExportAccessChecker $accessChecker,
        private readonly int $defaultEndTimeDifference,
    ) {
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        System::loadLanguageFile('tl_content');
        $arrAllCalendars = CalendarModel::findMultipleByIds(StringUtil::deserialize($model->ical_calendar, true));
        $ical = $this->getIcalFile($model, $this->accessChecker->filterCalendars($arrAllCalendars));

        if (!empty($ical)) {
            if ((string) $model->id === Input::get('ical')) {
                $filename = StringUtil::sanitizeFileName($model->ical_title ?? $model->id).'.ics';

                throw $this->responseUtil->sendFileForDownload($ical->createCalendar(), $filename);
            }

            // Generate a general HTML output using the download template
            $downloadTemplate = 'ce_download';
            if (!empty($model->ical_download_template)) {
                $request = $this->requestStack->getCurrentRequest();

                // Use the custom template unless it is a back end request
                if (!$request || !System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
                    $downloadTemplate = $model->ical_download_template;
                }
            }

            $tpl = new FrontendTemplate($downloadTemplate);
            $tpl->link = !empty($model->linkTitle) ? $model->linkTitle : $GLOBALS['TL_LANG']['tl_content']['ical_download_title'];
            $tpl->title = $GLOBALS['TL_LANG']['tl_content']['ical_download_title'];
            $tpl->href = Controller::addToUrl('ical='.$model->id);
            $tpl->filesize = System::getReadableSize(\strlen($ical->createCalendar()));
            $tpl->mime = 'text/calendar';
            $tpl->extension = 'ics';
            $template->downloadElement = $tpl->parse();

            return $this->createResponse($template, $arrAllCalendars);
        }

        $template->error = $GLOBALS['TL_LANG']['tl_content']['error_generating_ical_file'];

        return $this->createResponse($template, $arrAllCalendars);
    }

    /**
     * @param Collection<CalendarModel>|null $arrAllCalendars
     */
    private function createResponse(Template $template, Collection|null $arrAllCalendars): Response
    {
        $response = $template->getResponse();

        // The output depends on the logged in member and must not be stored in a shared cache
        if ($this->accessChecker->hasProtectedCalendar($arrAllCalendars)) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }

    /**
     * @param array<CalendarModel> $arrCalendars
     */
    private function getIcalFile(ContentModel $model, array $arrCalendars): Vcalendar|null
    {
        $startDate = !empty($model->ical_start) ? (int) $model->ical_start : time();
        $endDate = !empty($model->ical_end) ? (int) $model->ical_end : time() + $this->defaultEndTimeDifference * 24 * 3600;

        if (!empty($arrCalendars)) {
            return $this->icsExport->getVcalendar($arrCalendars, $startDate, $endDate, $model->ical_title, $model->ical_description, $model->ical_prefix);
        }

        return null;
    }
}
