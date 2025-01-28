<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2025, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Controller\Page\IcsFeedController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

class PageListener
{
    #[AsHook('getPageStatusIcon')]
    public function getStatusIcon(object $page, string $image): string
    {
        if (IcsFeedController::TYPE !== $page->type) {
            return $image;
        }

        return 'bundles/cgoitcontaocalendarical/ics.svg';
    }
}
