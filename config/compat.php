<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Contao\CoreBundle\Controller\AbstractBackendController as LegacyBackendController;
use Contao\CoreBundle\Controller\Backend\AbstractBackendController;

/*
 * Contao 5.7 moved the base class of back end controllers to "Contao\CoreBundle\Controller\Backend" and deprecated
 * the old class. As long as Contao 5.3 is supported, the new name is made available there, so that controllers
 * can extend the new class on all versions.
 */
if (!class_exists(AbstractBackendController::class)) {
    class_alias(LegacyBackendController::class, AbstractBackendController::class);
}
