<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Cgoit\ContaoCalendarIcalBundle\Backend\Automator;
use Cgoit\ContaoCalendarIcalBundle\Backend\ImportFileController;

/*
 * Backend modules
 */
$GLOBALS['BE_MOD']['content']['calendar']['import'] = [ImportFileController::class, 'importCalendar'];

$GLOBALS['TL_PURGE']['custom']['calendar_ical_regen_subscriptions'] = ['callback' => [Automator::class, 'generateIcsFiles']];
