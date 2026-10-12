<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests;

use Cgoit\ContaoCalendarIcalBundle\Controller\Backend\ImportBackendController;
use Contao\CoreBundle\Controller\Backend\AbstractBackendController;
use PHPUnit\Framework\TestCase;

final class CompatTest extends TestCase
{
    public function testTheBaseClassOfBackEndControllersIsAvailableUnderTheNewName(): void
    {
        // On Contao 5.3 the name is an alias of the old class (see config/compat.php), on Contao 5.7 it is the class itself
        $this->assertTrue(class_exists(AbstractBackendController::class));
        $this->assertTrue((new \ReflectionClass(ImportBackendController::class))->isSubclassOf(AbstractBackendController::class));
    }
}
