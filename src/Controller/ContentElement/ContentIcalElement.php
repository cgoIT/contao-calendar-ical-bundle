<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2023, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement;

use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Kigkonsult\Icalcreator\Vcalendar;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[AsContentElement(type: ContentIcalElement::TYPE, category: 'files')]
class ContentIcalElement extends AbstractContentElementController
{
    final public const TYPE = 'ical';

    protected string $strTitle = '';

    protected Vcalendar $ical;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly IcsExport $icsExport,
    ) {
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): Response
    {
        System::loadLanguageFile('tl_content');
        $ical = $this->getIcalFile($model);

        if (!empty($ical)) {
            if ((string) $model->id === Input::get('ical')) {
                $filename = StringUtil::sanitizeFileName($model->ical_title ?? $model->id).'.ics';
                $icalResponse = new Response($ical->createCalendar());
                $icalResponse->headers->set('Content-Type', 'text/calendar');
                $icalResponse->headers->set('Content-Disposition',
                    HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename,
                        $this->getFilenameFallback($filename)));

                throw new ResponseException($icalResponse);
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

            return $template->getResponse();
        }

        $template->error = $GLOBALS['TL_LANG']['tl_content']['error_generating_ical_file'];

        return $template->getResponse();
    }

    private function getIcalFile(ContentModel $model): Vcalendar|null
    {
        $startDate = !empty($model->ical_start) ? (int) $model->ical_start : time();
        $endDate = !empty($model->ical_end) ? (int) $model->ical_end : time() + 365 * 24 * 3600;

        $arrCalendars = CalendarModel::findMultipleByIds(StringUtil::deserialize($model->ical_calendar, true));
        if (!empty($arrCalendars)) {
            return $this->icsExport->getVcalendar($arrCalendars, $startDate, $endDate, $model->ical_title, $model->ical_description, $model->ical_prefix);
        }

        return null;
    }

    private function getFilenameFallback(string $filename): string
    {
        if (!preg_match('/^[\x20-\x7e]*$/', $filename) || str_contains($filename, '%')) {
            $filenameFallback = '';
            $encoding = mb_detect_encoding($filename, null, true) ?: '8bit';

            for ($i = 0, $filenameLength = mb_strlen($filename, $encoding); $i < $filenameLength; ++$i) {
                $char = mb_substr($filename, $i, 1, $encoding);

                if ('%' === $char || \ord($char) < 32 || \ord($char) > 126) {
                    $filenameFallback .= '_';
                } else {
                    $filenameFallback .= $char;
                }
            }

            return $filenameFallback;
        }

        return $filename;
    }
}
