<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Controller\Backend\ImportBackendController;
use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The button of the event list that opens the import of the calendar.
 */
#[AsCallback(table: 'tl_calendar_events', target: 'list.global_operations.import.button')]
class ImportButtonCallback
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function __invoke(string|null $href, string $label, string $title, string $class, string $attributes): string
    {
        // In the list of the events the id is the one of the calendar
        $calendarId = (int) $this->requestStack->getCurrentRequest()?->query->get('id');

        if ($calendarId < 1 || !$this->security->isGranted(ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR, $calendarId)) {
            return '';
        }

        $url = $this->router->generate(ImportBackendController::ROUTE_NAME, ['id' => $calendarId]);

        return '<a href="'.StringUtil::specialcharsUrl($url).'" class="'.$class.'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.$label.'</a> ';
    }
}
