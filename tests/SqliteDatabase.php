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
