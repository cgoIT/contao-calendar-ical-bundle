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

use Contao\Database;
use Contao\Database\Statement;
use Doctrine\DBAL\Connection;

/**
 * Contao's database wrapper for tests with SQLite: the column list does not come from MySQL's "SHOW COLUMNS".
 */
final class SqliteDatabase extends Database
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    /**
     * SQLite reports the rows changed by the last write as the row count of the next SELECT. A statement that
     * changes nothing is run first, so that Contao finds out the number of rows by counting them.
     */
    public function prepare($strQuery): Statement
    {
        $connection = $this->connection;

        $statement = new class($connection, $connection) extends Statement {
            public function __construct(
                Connection $doctrine,
                private readonly Connection $connection,
            ) {
                parent::__construct($doctrine);
            }

            public function execute(): mixed
            {
                $this->connection->executeStatement('DELETE FROM tl_calendar WHERE 0');

                return parent::execute(...\func_get_args());
            }
        };

        return $statement->prepare($strQuery);
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    public function listFields($strTable, $blnNoCache = false): array
    {
        $fields = [];

        foreach ($this->connection->createSchemaManager()->listTableColumns((string) $strTable) as $column) {
            $fields[] = ['name' => $column->getName(), 'type' => 'column'];
        }

        return $fields;
    }
}
