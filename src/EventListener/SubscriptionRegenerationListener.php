<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener;

use Cgoit\ContaoCalendarIcalBundle\Backend\ExportController;
use Contao\CalendarModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Collects the calendars whose subscription file has to be rebuilt and does it once after the response was sent.
 * A change of many events (e.g. in the "edit multiple" mode) rebuilds the file of a calendar only once, and a
 * deleted event is no longer part of the file because the records are deleted by then.
 */
class SubscriptionRegenerationListener
{
    /**
     * @var array<int, true>
     */
    private array $calendarIds = [];

    private bool $all = false;

    public function __construct(
        private readonly ExportController $calendarExport,
        #[Autowire(service: 'monolog.logger.contao.general')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function schedule(int $calendarId): void
    {
        $this->calendarIds[$calendarId] = true;
    }

    public function scheduleAll(): void
    {
        $this->all = true;
    }

    #[AsEventListener]
    public function onTerminate(TerminateEvent $event): void
    {
        $all = $this->all;
        $calendarIds = array_keys($this->calendarIds);

        $this->all = false;
        $this->calendarIds = [];

        try {
            if ($all) {
                $this->calendarExport->generateSubscriptions();

                return;
            }

            foreach ($calendarIds as $calendarId) {
                $calendar = CalendarModel::findById($calendarId);

                if (null !== $calendar) {
                    $calendar->refresh();
                    $this->calendarExport->generateSubscriptions($calendar);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Could not regenerate the ical subscriptions: '.$e->getMessage());
        }
    }
}
