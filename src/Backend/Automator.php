<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Backend;

use Contao\Automator as ContaoAutomator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Automator
{
    public function __construct(
        private readonly ExportController $calendarExport,
        #[Autowire(service: 'monolog.logger.contao.cron')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Regenerate the XML files.
     */
    public function generateIcsFiles(): void
    {
        $this->calendarExport->generateSubscriptions();

        // Also empty the shared cache so there are no links to deleted files
        (new ContaoAutomator())->purgePageCache();

        $this->logger->info('Regenerated the ICS files');
    }
}
