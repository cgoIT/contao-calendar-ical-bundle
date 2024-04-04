<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Backend;

use Contao\Automator as ContaoAutomator;
use Contao\System;

class Automator extends System
{
    private readonly ContaoAutomator $contaoAutomator;

    public function __construct(
        private readonly ExportController $calendarExport,
    ) {
        parent::__construct();
        $this->contaoAutomator = new ContaoAutomator();
    }

    /**
     * Regenerate the XML files.
     */
    public function generateIcsFiles(): void
    {
        $this->calendarExport->generateSubscriptions();

        // Also empty the shared cache so there are no links to deleted files
        $this->contaoAutomator->purgePageCache();

        System::getContainer()->get('monolog.logger.contao.cron')->info('Regenerated the ICS files');
    }
}
