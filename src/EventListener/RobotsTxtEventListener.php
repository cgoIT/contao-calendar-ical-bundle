<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\RobotsTxtEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use webignition\RobotsTxt\Directive\Directive;
use webignition\RobotsTxt\Directive\UserAgentDirective;
use webignition\RobotsTxt\Inspector\Inspector;
use webignition\RobotsTxt\Record\Record;

#[AsEventListener(ContaoCoreEvents::ROBOTS_TXT)]
class RobotsTxtEventListener
{
    public function __invoke(RobotsTxtEvent $event): void
    {
        $file = $event->getFile();
        $inspector = new Inspector($file);

        // Check if a "User-Agent: *" directive is not already present
        if (0 === $inspector->getDirectives()->getLength()) {
            $record = new Record();

            $userAgentDirectiveList = $record->getUserAgentDirectiveList();
            $userAgentDirectiveList->add(new UserAgentDirective('*'));

            $file->addRecord($record);
        }

        foreach ($file->getRecords() as $record) {
            $directiveList = $record->getDirectiveList();
            $directiveList->add(new Directive('Disallow', '/_event/'));
        }
    }
}
