<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Migration;

use Cgoit\ContaoCalendarIcalBundle\Migration\CalendarDateFieldsMigration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class CalendarDateFieldsMigrationTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testDoesNotRunWithoutTheTable(): void
    {
        $this->assertFalse((new CalendarDateFieldsMigration($this->db))->shouldRun());
    }

    public function testRunsIfTheDatesAreStoredAsText(): void
    {
        $this->db->executeStatement("CREATE TABLE tl_calendar (id INTEGER PRIMARY KEY, ical_start VARCHAR(12) DEFAULT '' NOT NULL, ical_end INTEGER DEFAULT NULL)");

        $this->assertTrue((new CalendarDateFieldsMigration($this->db))->shouldRun());
    }

    public function testDoesNotRunIfTheDatesAreIntegersAlready(): void
    {
        $this->db->executeStatement('CREATE TABLE tl_calendar (id INTEGER PRIMARY KEY, ical_start INTEGER DEFAULT NULL, ical_end INTEGER DEFAULT NULL, ical_source_start INTEGER DEFAULT NULL, ical_source_end INTEGER DEFAULT NULL)');

        $this->assertFalse((new CalendarDateFieldsMigration($this->db))->shouldRun());
    }
}
