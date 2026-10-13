<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

use Cgoit\ContaoCalendarIcalBundle\Controller\ContentElement\ContentIcalElement;
use Cgoit\ContaoCalendarIcalBundle\Controller\Page\IcsFeedController;

$GLOBALS['TL_LANG']['CTE'][ContentIcalElement::TYPE]['0'] = 'iCal';
$GLOBALS['TL_LANG']['CTE'][ContentIcalElement::TYPE]['1'] = 'Dieses Element erstellt einen Hyperlink auf eine vCalendar (iCal)-Datei, die heruntergeladen werden kann und dazu verwendet werden kann, die enthaltenen Termine in einer externen Kalenderanwendung anzuzeigen.';

$GLOBALS['TL_LANG']['PTY'][IcsFeedController::TYPE]['0'] = 'ics Feed';
$GLOBALS['TL_LANG']['PTY'][IcsFeedController::TYPE]['1'] = 'Dieser Seitentyp erzeugt einen ics-Feed für einen oder mehrere Kalender, der in eine externe Kalenderanwendung eingebunden werden kann.';

$GLOBALS['TL_LANG']['MSC']['import_calendar']['0'] = 'Kalender importieren';
$GLOBALS['TL_LANG']['MSC']['import_calendar']['1'] = 'Importieren Sie eine iCal (.ics) Kalender-Datei in Ihren Kalender.';

$GLOBALS['TL_LANG']['MSC']['export_calendar']['0'] = 'Kalender exportieren';
$GLOBALS['TL_LANG']['MSC']['export_calendar']['1'] = 'Importieren Sie Ihren Kalender in eine iCal (.ics) Kalender-Datei.';

$GLOBALS['TL_LANG']['MSC']['copy_to_clipboard'] = 'Link zur ics Datei in die Zwischenablage kopieren.';

$GLOBALS['TL_LANG']['MSC']['location'] = 'Ort';

$GLOBALS['TL_LANG']['tl_maintenance_jobs']['calendar_ical_regen_subscriptions'][0] = 'ICS-Dateien neu schreiben';
$GLOBALS['TL_LANG']['tl_maintenance_jobs']['calendar_ical_regen_subscriptions'][1] = 'Schreibt die ICS-Dateien im Ordner <code>shared</code> neu und leert anschließend den Shared-Cache, damit keine ungültigen Links zurückbleiben.';

$GLOBALS['TL_LANG']['ERR']['only_one_file'] = 'Bitte wählen Sie nur eine Datei zum Hochladen aus!';
$GLOBALS['TL_LANG']['ERR']['ics_parse_error'] = 'Die hochgeladene ics-Datei konnte aufgrund eines Formatfehlers nicht verarbeitet werden.';
$GLOBALS['TL_LANG']['ERR']['ical_protected_calendar'] = 'Für geschützte Kalender kann kein iCal-Abonnement erstellt werden, da die Datei öffentlich im Verzeichnis „share“ abgelegt würde. Nutzen Sie stattdessen eine Seite vom Typ „iCal-Feed“.';
$GLOBALS['TL_LANG']['ERR']['ical_invalid_pattern'] = 'Der reguläre Ausdruck für die Titel-Umschreibung ist ungültig (er benötigt Trennzeichen, z. B. /muster/): %s';
$GLOBALS['TL_LANG']['ERR']['csv_records_skipped'] = '%d Datensätze wurden nicht importiert, weil das Datum oder die Uhrzeit nicht dem gewählten Format entspricht (Datensatz Nr.: %s).';
$GLOBALS['TL_LANG']['MSC']['ical_import_running'] = 'Die iCal-Quelle wird gerade bereits importiert. Die geänderten Einstellungen werden beim nächsten Import übernommen.';
$GLOBALS['TL_LANG']['ERR']['csv_parse_error'] = 'Die CSV-Datei konnte nicht gelesen werden.';
$GLOBALS['TL_LANG']['ERR']['csv_no_mapping'] = 'Es wurde keine Spalte einem Feld zugeordnet. Bitte ordnen Sie mindestens eine Spalte zu.';
$GLOBALS['TL_LANG']['ERR']['import_failed'] = 'Beim Import ist ein Fehler aufgetreten. Details stehen im System-Log.';
