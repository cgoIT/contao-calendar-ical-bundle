<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;

#[AsCallback(table: 'tl_content', target: 'fields.ical_calendar.options')]
#[AsCallback(table: 'tl_page', target: 'fields.ical_calendar.options')]
class CalendarOptionsListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Security $security,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(DataContainer $dc): array
    {
        $user = $this->security->getUser();

        $qb = $this->connection->createQueryBuilder()
            ->select('id, title')
            ->from('tl_calendar')
        ;

        if ($user instanceof BackendUser && !$this->security->isGranted('ROLE_ADMIN')) {
            $qb->where($qb->expr()->in('id', $user->calendars));
        }

        $results = $qb->executeQuery();

        $options = [];

        foreach ($results->fetchAllAssociative() as $archive) {
            $options[$archive['id']] = $archive['title'];
        }

        return $options;
    }
}
