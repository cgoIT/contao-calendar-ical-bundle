<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-php8-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2024, cgoIT
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
