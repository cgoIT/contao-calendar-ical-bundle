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

use Cgoit\ContaoCalendarIcalBundle\Import\CsvImport;
use Cgoit\ContaoCalendarIcalBundle\Tests\DatabaseTestCase;
use Cgoit\ContaoCalendarIcalBundle\Util\UploadedFileResolver;
use Contao\Date;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the import of a csv file against a real (SQLite) database, so that the events are saved and read back.
 */
final class CsvImportDatabaseTest extends DatabaseTestCase
{
    private const array MAPPING = [[0, 'title'], [1, 'startDate'], [2, 'startTime'], [3, 'details']];

    public function testImportsTheRecordsOfAFile(): void
    {
        $skipped = $this->import("Titel;Datum;Zeit;Text\nTraining;01.05.2024;18:30;Mit <b>Ball</b>\nSpiel;08.05.2024;;\n");

        $this->assertSame([], $skipped);

        [$training, $game] = $this->events();

        $this->assertSame('Training', $training['title']);
        $this->assertSame('training', $training['alias']);
        $this->assertSame(1, (int) $training['pid']);
        $this->assertSame(7, (int) $training['author']);
        $this->assertSame('1', $training['published']);
        $this->assertSame('default', $training['source']);
        $this->assertSame(mktime(0, 0, 0, 5, 1, 2024), (int) $training['startDate']);
        $this->assertSame(mktime(0, 0, 0, 5, 1, 2024), (int) $training['endDate'], 'without an end date the event ends on its first day');
        $this->assertSame(mktime(18, 30, 0, 5, 1, 2024), (int) $training['startTime']);
        $this->assertSame('1', $training['addTime']);

        $texts = $this->db->fetchFirstColumn("SELECT text FROM tl_content WHERE ptable = 'tl_calendar_events' AND pid = ".(int) $training['id']);
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('&lt;b&gt;Ball&lt;/b&gt;', (string) $texts[0]);

        $this->assertSame('Spiel', $game['title']);
        $this->assertEmpty($game['addTime']);
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_content'));
    }

    public function testSkipsRecordsWithAWrongDateOrTimeAndReportsThem(): void
    {
        $skipped = $this->import("Titel;Datum;Zeit;Text\nOk;01.05.2024;18:30;\nFalsches Datum;31.02.2024;18:30;\nFalsche Zeit;02.05.2024;25:99;\nAuch ok;03.05.2024;;\n");

        $this->assertSame([3, 4], $skipped);
        $this->assertSame(['Ok', 'Auch ok'], array_column($this->events(), 'title'));
    }

    public function testImportsOnlyRecordsWhoseTitleContainsTheFilter(): void
    {
        $this->import("Titel;Datum;Zeit;Text\nJugend Training;01.05.2024;;\nSpiel;02.05.2024;;\n", filterEventTitle: 'TRAINING');

        $this->assertSame(['Jugend Training'], array_column($this->events(), 'title'));
    }

    public function testImportsOnlyRecordsInThePeriod(): void
    {
        $this->import(
            "Titel;Datum;Zeit;Text\nZu frueh;01.04.2024;;\nDrin;15.05.2024;;\nZu spaet;01.07.2024;;\n",
            start: new Date(mktime(0, 0, 0, 5, 1, 2024), 'Y-m-d'),
            end: new Date(mktime(0, 0, 0, 5, 31, 2024), 'Y-m-d'),
        );

        $this->assertSame(['Drin'], array_column($this->events(), 'title'));
    }

    public function testAppliesTheTimeShiftAndUnpublishedState(): void
    {
        $this->db->executeStatement("UPDATE tl_calendar SET ical_publish_imported = '0' WHERE id = 1");

        $this->import("Titel;Datum;Zeit;Text\nTraining;01.05.2024;18:30;\n", timeshift: 2);

        $event = $this->events()[0];

        $this->assertSame(mktime(2, 0, 0, 5, 1, 2024), (int) $event['startDate']);
        $this->assertSame(mktime(20, 30, 0, 5, 1, 2024), (int) $event['startTime']);
        $this->assertEmpty($event['published']);
    }

    public function testNamesRecordsWithoutATitle(): void
    {
        $this->import("Titel;Datum;Zeit;Text\n;01.05.2024;;\n");

        $this->assertSame('Untitled', $this->events()[0]['title']);
    }

    public function testReplacesTheExistingEventsOfTheCalendar(): void
    {
        $this->import("Titel;Datum;Zeit;Text\nAlt;01.05.2024;;Text\n");
        $this->import("Titel;Datum;Zeit;Text\nNeu;02.05.2024;;\n", deleteCalendar: true);

        $this->assertSame(['Neu'], array_column($this->events(), 'title'));
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM tl_content'), 'the text element of the removed event is gone');
    }

    public function testKeepsTheExistingEventsIfTheFileHasNoHeader(): void
    {
        $this->import("Titel;Datum;Zeit;Text\nAlt;01.05.2024;;\n");

        try {
            $this->import('', deleteCalendar: true);
            $this->fail('the exception is expected');
        } catch (\InvalidArgumentException) {
            $this->assertSame(['Alt'], array_column($this->events(), 'title'));
        }
    }

    public function testRollsBackEverythingIfAnEventCannotBeSaved(): void
    {
        $this->import("Titel;Datum;Zeit;Text\nAlt;01.05.2024;;\n");

        // The second record fails in the database: the whole import including the deletion is undone
        $this->db->executeStatement('CREATE TRIGGER fail BEFORE INSERT ON tl_calendar_events WHEN NEW.title = \'Kaputt\' BEGIN SELECT RAISE(ABORT, \'boom\'); END');

        try {
            $this->import("Titel;Datum;Zeit;Text\nNeu;02.05.2024;;\nKaputt;03.05.2024;;\n", deleteCalendar: true);
            $this->fail('the exception is expected');
        } catch (\Throwable) {
            $this->assertSame(['Alt'], array_column($this->events(), 'title'));
        }
    }

    /**
     * @return list<int>
     */
    private function import(string $csv, bool $deleteCalendar = false, int $timeshift = 0, string $filterEventTitle = '', Date|null $start = null, Date|null $end = null): array
    {
        $dir = $this->getTempDir();
        (new Filesystem())->dumpFile($dir.'/system/tmp/events.csv', $csv);

        $import = new CsvImport($this->db, $dir, $this->createSlug(), new UploadedFileResolver($dir));

        return $import->import(
            'system/tmp/events.csv',
            1,
            self::MAPPING,
            'utf8',
            'd.m.Y',
            'H:i',
            $start ?? new Date(mktime(0, 0, 0, 1, 1, 2024), 'Y-m-d'),
            $end,
            $deleteCalendar,
            $timeshift,
            $filterEventTitle,
        );
    }
}
