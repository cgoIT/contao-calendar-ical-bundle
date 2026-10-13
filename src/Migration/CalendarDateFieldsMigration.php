<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\StringType;

/**
 * The dates of the iCal export and of the web source were stored as text. They are time stamps and
 * are now stored as (nullable) integers like the dates of the Contao core. The conversion of the
 * column is done here because the automatic schema update cannot convert an empty string into a number.
 */
class CalendarDateFieldsMigration extends AbstractMigration
{
    private const array COLUMNS = ['ical_start', 'ical_end', 'ical_source_start', 'ical_source_end'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        return [] !== $this->getTextColumns();
    }

    public function run(): MigrationResult
    {
        $columns = $this->getTextColumns();

        foreach ($columns as $column) {
            // Empty values (and anything that is no time stamp or too large for the column) become NULL, the others keep their value
            $this->connection->executeStatement("ALTER TABLE tl_calendar MODIFY $column varchar(12) NULL DEFAULT NULL");
            $this->connection->executeStatement("UPDATE tl_calendar SET $column = NULL WHERE $column NOT REGEXP '^[0-9]{1,10}$' OR CAST($column AS UNSIGNED) > 4294967295");
            $this->connection->executeStatement("ALTER TABLE tl_calendar MODIFY $column INT UNSIGNED NULL DEFAULT NULL");
        }

        return $this->createResult(true, 'Converted the columns '.implode(', ', $columns).' of tl_calendar to integers.');
    }

    /**
     * @return list<string> The columns that still have a text type
     */
    private function getTextColumns(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_calendar'])) {
            return [];
        }

        $columns = [];

        foreach ($schemaManager->listTableColumns('tl_calendar') as $column) {
            if (\in_array($column->getName(), self::COLUMNS, true) && $column->getType() instanceof StringType) {
                $columns[] = $column->getName();
            }
        }

        return $columns;
    }
}
