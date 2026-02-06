<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('cgoit_contao_calendar_ical');

        $treeBuilder->getRootNode()
            ->children()
            ->integerNode('end_date_time_difference_in_days')
            ->info('The difference in days between start and end date. This difference is used if an event is exported and no end date is given in the event. Default to 365.')
            ->min(1)
            ->defaultValue('365')
            ->end()

            ->end()
        ;

        return $treeBuilder;
    }
}
