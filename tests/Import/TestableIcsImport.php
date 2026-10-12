<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Import;

use Cgoit\ContaoCalendarIcalBundle\Import\IcsImport;
use Contao\CalendarModel;
use Contao\Date;
use Doctrine\DBAL\Connection;
use Kigkonsult\Icalcreator\Vcalendar;

/**
 * IcsImport without the import of the events (and without the constructor) for the tests of the lock and the transaction.
 */
final class TestableIcsImport extends IcsImport
{
    public int $imports = 0;

    public \Closure|null $beforeImport = null;

    public bool $failAfterWrite = false;

    public Connection $connection;

    public function __construct(private readonly bool $throw)
    {
    }

    public function acquire(CalendarModel $calendar): bool
    {
        return $this->acquireImportLock($calendar);
    }

    /**
     * Runs the transaction handling of the parent; the events are replaced by a write to the database.
     */
    public function importFromIcsFileUsingRealTransaction(Vcalendar $cal, CalendarModel $calendar, Date $start, Date $end, bool $fail): void
    {
        $this->failAfterWrite = $fail;
        parent::importFromIcsFile($cal, $calendar, $start, $end, true, null, null, null);
    }

    public function importFromIcsFile(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        if (null !== $this->beforeImport) {
            ($this->beforeImport)();
        }

        if ($this->throw) {
            throw new \RuntimeException('import failed');
        }

        ++$this->imports;
    }

    protected function importEvents(Vcalendar $cal, CalendarModel $objCalendar, Date $startDate, Date $endDate, array|bool $tz, string|null $filterEventTitle, string|null $patternEventTitle, string|null $replacementEventTitle, bool $deleteCalendar = false, int $timeshift = 0): void
    {
        $this->connection->insert('tl_calendar', ['id' => 2, 'title' => 'written by the import', 'tstamp' => time()]);

        if ($this->failAfterWrite) {
            throw new \RuntimeException('import failed');
        }
    }
}
