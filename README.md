[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcontao-calendar-ical-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)

Contao 5 Calendar iCal Bundle
=============================

iCal-Unterstützung für den Kalender von Contao Open Source CMS. Fork von https://github.com/Craffft/contao-calendar-ical-bundle.

Installation
------------

Öffnen Sie eine Konsole, wechseln Sie in Ihr Projektverzeichnis und führen Sie den folgenden Befehl aus, um die
neueste stabile Version des Bundles zu installieren:

```bash
$ composer require cgoit/contao-calendar-ical-bundle
```

Dafür muss Composer global installiert sein, wie im
[Installationskapitel](https://getcomposer.org/doc/00-intro.md) der Composer-Dokumentation beschrieben.

Unterstützte Versionen
----------------------

Das Bundle benötigt PHP 8.3 oder neuer und unterstützt Contao 5 (ab 5.3) und Symfony 6.4 bzw. 7.3 oder neuer. Contao 4.13 und Symfony 5.4 werden nicht
mehr unterstützt; für Contao 4 verwenden Sie bitte eine ältere Version des Bundles.

Einzelne Termine als ICS exportieren
------------------------------------

Um einen einzelnen Termin als ICS zu exportieren (z. B. damit Besucher ihn in den eigenen Kalender importieren können),
können Sie einen Link auf die URL `/_event/ics-export/{id}` setzen, wobei `id` die ID des Termins ist. Alternativ
können Sie den Routennamen `event_frontend_ics_export` mit dem Parameter `id` verwenden.

Zum Beispiel können Sie das folgende Snippet in Ihr Template `event_full.html5` einfügen:

```html
<a href="<?= $this->route('event_frontend_ics_export', ['id' => $this->id]) ?>" class="ics-export" rel="nofollow" title="Termin als ICS exportieren">
    <img src="/bundles/cgoitcontaocalendarical/ics.svg" width="32" height="32" alt="">
</a>
```

Eventliste als ICS exportieren
------------------------------

Um eine Eventliste (Modul) als ICS zu exportieren (z. B. damit Besucher sie in den eigenen Kalender importieren
können), können Sie einen Link auf die URL `/_eventlist/ics-export/{id}` setzen, wobei `id` die ID des
Eventlisten-Moduls ist. Alternativ können Sie den Routennamen `eventlist_frontend_ics_export` mit dem Parameter `id`
verwenden.

Zum Beispiel können Sie das folgende Snippet in Ihr Template `mod_eventlist.html5` einfügen:

```html
<a href="<?= $this->route('eventlist_frontend_ics_export', ['id' => $this->id]) ?>" class="ics-export" rel="nofollow" title="Als ICS exportieren">
  <img src="/bundles/cgoitcontaocalendarical/ics.svg" width="32" height="32" alt="">
</a>
```

Abonnement-Dateien und Feeds
----------------------------

Mit der Option „iCal Abonnement erstellen“ schreibt das Bundle die Datei `share/<Alias>.ics` (ohne Alias:
`share/calendar<ID>.ics`). Beim Neuaufbau werden nur diese Dateien ersetzt oder gelöscht; andere Dateien in `share/`
bleiben unberührt. Die Datei wird nach dem Speichern, Kopieren, Löschen, Wiederherstellen und Veröffentlichen von Terminen
(einmal pro Anfrage, nach dem Senden der Antwort), beim Speichern des Kalenders, täglich per Cron und über die
Systemwartung neu erzeugt. Beginnt oder endet die Veröffentlichung eines Termins zu einer bestimmten Zeit, wirkt sich das
erst beim nächsten Neuaufbau aus. Alternativ liefert eine Seite vom Typ `ics_feed` oder das Inhaltselement „iCal“ die Termine
ausgewählter Kalender. Ein dort eingetragenes Präfix wird dem Titel jedes Termins vorangestellt; ohne Angabe gilt das
Präfix des jeweiligen Kalenders.

Wiederholungen, Ausnahmen und Teilnehmer
----------------------------------------

Mit dem `cgoit/calendar-extended-bundle` ausgeschlossene Wiederholungen („Termin nicht anzeigen“) werden als `EXDATE`
exportiert. Verschobene Wiederholungen („Termin verschieben“) werden als eigener Termin mit derselben `UID` und
`RECURRENCE-ID` des ursprünglichen Termins exportiert; Kalender-Apps ersetzen damit genau diese eine Wiederholung.

Als `ATTENDEE` kann ICS nur E-Mail-Adressen aufnehmen. Teilnehmer ohne gültige E-Mail-Adresse werden daher nicht
exportiert.

Geschützte Kalender
-------------------

Geschützte Kalender (`protected` mit Mitgliedergruppen) werden nur an Frontend-Mitglieder der erlaubten Gruppen
exportiert. Das gilt für den Export einzelner Termine, den Eventlisten-Export, die Seite vom Typ `ics_feed` und das
Inhaltselement „iCal“. Unveröffentlichte Termine werden nie exportiert. Eine Eventliste (Modul oder Inhaltselement) wird nur exportiert, wenn
sie für den Besucher sichtbar ist; bei Inhaltselementen gilt das auch für den Artikel, die Seite und übergeordnete
Elemente (veröffentlicht, Zugriffsschutz).

Kalender-Apps melden sich nicht als Contao-Mitglied an. Ein Feed mit geschützten Kalendern enthält für sie daher keine
Termine dieser Kalender; er wird zudem nie in einem gemeinsamen Cache abgelegt.

Die Option „iCal Abonnement erstellen“ schreibt eine **öffentliche** Datei nach `share/`. Sie steht daher für
geschützte Kalender nicht zur Verfügung; verwenden Sie stattdessen eine Seite vom Typ `ics_feed`.

Kalender im Backend importieren (ICS und CSV)
---------------------------------------------

In der Terminliste eines Kalenders öffnet die Schaltfläche „Kalender importieren“ (Menü „…“ in neueren Contao-Versionen)
den Import. Er steht Benutzern zur Verfügung, die das Modul „Events“ nutzen und den Kalender bearbeiten dürfen.

1. **Datei und Einstellungen:** Wählen Sie eine ICS- oder CSV-Datei, den Zeitraum (Startdatum und Enddatum; ohne Enddatum
   gilt der 31.12. des übernächsten Jahres, bei ICS und CSV gleichermaßen), eine
   manuelle Zeitverschiebung in Stunden, ob die bestehenden Termine des Kalenders vorher entfernt werden sollen und
   optional einen **Termin-Titel-Filter**: Nur Termine, deren Titel diese Zeichenkette enthält (ohne Beachtung der
   Groß-/Kleinschreibung), werden importiert.
2. **ICS – Zeitzone:** Unterscheidet sich die Zeitzone der Datei von der Zeitzone des Systems, oder enthält die Datei keine,
   fragt der Import nach: Sie können die Zeitzone der Datei durch die des Systems ersetzen („Zeitzone korrigieren“)
   bzw. eine Zeitzone für die Termine wählen.
3. **CSV – Spalten zuordnen:** Jede Spalte der Datei wird einem Feld des Termins zugeordnet (Titel, Start/Ende, Beschreibung,
   Ort, ...). Spalten, deren Name einem Feld entspricht, sind vorbelegt. Zeichencodierung, Datums- und Zeitformat sind
   einstellbar; „Überprüfen“ liest die Vorschau mit den geänderten Einstellungen neu ein.

Die hochgeladene Datei bleibt nur bis zum Ende des Imports (oder einer Stunde) liegen. „Abbrechen“ verwirft sie sofort.
Werden bei „Bestehende Termine entfernen“ keine Termine gelesen (leere oder unlesbare Datei, keine Spalte zugeordnet),
bleibt der Kalender unverändert.

Kalender aus einer Web-Quelle importieren
-----------------------------------------

Ein Kalender kann seine Termine regelmäßig aus einer ICS-URL übernehmen („Web-Quelle“).

Mit „Termin-Titel filtern“ (`ical_filter_event_title`) werden nur Termine importiert, deren Titel die Zeichenkette enthält;
„Termin-Titel Pattern“ und „Replacement“ schreiben den Titel mit einem regulären Ausdruck um (siehe unten).

Ab Version 6.0 werden Web-Quellen **nicht mehr beim Seitenaufruf**, sondern nur noch im Hintergrund aktualisiert: Der
Contao-Cron (stündlich) importiert jeden Kalender, dessen Cache-Dauer („Cache-Dauer in Sekunden“, Standard 86400)
abgelaufen ist. Der Contao-Cron muss daher eingerichtet sein (Systemcron oder Poor-Man's-Cron). Beim Speichern des
Kalenders im Backend wird die Quelle sofort abgerufen, damit geänderte Einstellungen (URL, Filter, Zeitzone, ...) gleich
wirksam werden. Ein fehlgeschlagener Abruf wird beim nächsten Cron-Lauf erneut versucht. Ein Import läuft nie doppelt (Sperre in der
Datenbank, eine verwaiste Sperre verfällt nach zwei Minuten) und ersetzt die Termine des Kalenders in einer Transaktion:
Bei einem Fehler bleiben die bisherigen Termine erhalten. Hat der Kalender ein Abonnement, wird auch dessen Datei neu
erzeugt.

Der Download ist ab Version 6.0 abgesichert:

- Erlaubt sind nur `http://`, `https://` und `webcal://`. `webcal://` wird immer als `https://` abgerufen; Quellen, die
  nur per HTTP erreichbar sind, tragen Sie bitte mit `http://` ein.
- Das TLS-Zertifikat der Gegenstelle wird **geprüft**. Quellen mit selbstsigniertem oder abgelaufenem Zertifikat
  lassen sich nicht mehr importieren; die Gegenstelle muss ein gültiges Zertifikat verwenden.
- Ziele in privaten Netzen (z. B. `127.0.0.1`, `192.168.x.x`, Cloud-Metadaten-Adressen) werden ohne Proxy blockiert.
  Das gilt auch für Weiterleitungen. Ist ein Proxy konfiguriert, löst dieser das Ziel auf; dann findet die Prüfung nicht
  statt.
- Zeitlimit 10 Sekunden je Anfrage (30 Sekunden gesamt), höchstens 5 Weiterleitungen, höchstens 10 MiB Dateigröße.
- Der Inhalt wird direkt verarbeitet; es werden keine temporären Dateien in `system/tmp` mehr angelegt.

Die PHP-Erweiterung `ext-curl` wird nicht mehr benötigt, stattdessen `symfony/http-client`.

CSV-Import
----------

Der CSV-Import im Backend erkennt das Trennzeichen (`,` oder `;`) anhand der Kopfzeile, überspringt Leerzeilen und
unterstützt Felder mit Zeilenumbrüchen in Anführungszeichen. Ein UTF-8-BOM wird entfernt. Die Auswahl „ISO-8859-1
(Windows)“ liest Windows-1252 (damit auch z. B. das Euro-Zeichen).

Datum und Uhrzeit werden streng nach dem gewählten Format geprüft. Datensätze mit einem ungültigen Datum oder einer
ungültigen Uhrzeit werden nicht importiert; nach dem Import zeigt eine Meldung die Nummern dieser Datensätze. Der Import
läuft in einer Transaktion: Bei einem Fehler bleibt der Kalender unverändert, auch wenn „Kalender leeren“ gewählt war.

Importierte Inhalte
-------------------

Titel, Ort, Beschreibung, Teilnehmer und Kontakte aus ICS- und CSV-Dateien werden beim Import kodiert gespeichert
(HTML wird als Text angezeigt, Insert-Tags werden entfernt). Das gilt für den Web-Import wie für den Import im Backend.
Bereits importierte Termine werden beim nächsten Import entsprechend aktualisiert.

Datumswerte ohne Uhrzeit (ganztägige Termine) und Zeiten ohne Zeitzone („floating time“) werden in der Zeitzone des
Kalenders gelesen (Web-Quelle: Einstellung „Zeitzone“; sonst Zeitzone der ICS-Datei bzw. des Systems). Zeiten in UTC oder
mit `TZID` behalten ihre Zeitzone.

Bei Wiederholungen gilt `UNTIL` einschließlich der letzten Wiederholung; es wird als UTC-Zeitpunkt gelesen (bei
ganztägigen Terminen und Zeiten ohne Zeitzone als Ortszeit des Kalenders). Eine Serie mit `COUNT=1` ist ein einzelner
Termin. Gibt ein Feed `UNTIL` nur als Datum an, obwohl der Termin eine Uhrzeit hat, gilt die Wiederholung dieses Tages noch.

Der reguläre Ausdruck für die Titel-Umschreibung wird beim Speichern des Kalenders geprüft und benötigt Trennzeichen,
z. B. `/^\[Verein\] /`. Das Feld „Benutzername:Passwort“ für den Proxy wird verdeckt angezeigt und ist nicht
durchsuchbar. Hochgeladene Dateien für den Backend-Import werden nach dem Import aus `system/tmp` gelöscht.

Erweiterungspunkte
------------------

Das Bundle stellt Symfony-Events bereit (Namespace `Cgoit\ContaoCalendarIcalBundle\Event`), die Sie mit einem
`#[AsEventListener]` abonnieren können:

| Event | Zeitpunkt |
|---|---|
| `BeforeImportItemEvent` | Vor dem Import eines Termins; mit `$event->skip = true` wird der Termin übersprungen, der Termin (`calendarEventModel`) kann angepasst werden. |
| `AfterImportItemEvent` | Nach dem Import eines Termins. |
| `ModifyExportEventsEvent` | Vor dem Export der Termine eines Kalenders; `$event->events` kann gefiltert oder ergänzt werden. |
| `ModifyVeventEvent` | Für jeden exportierten Termin; `$event->vevent` kann angepasst oder ersetzt werden. |

Die Hooks `$GLOBALS['TL_HOOKS']['icalGetAllEvents']` und `$GLOBALS['TL_HOOKS']['icalModifyVevent']` funktionieren
weiterhin, sind aber deprecated. Bitte stellen Sie auf `ModifyExportEventsEvent` bzw. `ModifyVeventEvent` um.

Wichtig
-------

**Update auf Version 6.0 (Breaking Changes beim Import im Backend):** Der Import im Backend ist eine neue Seite
(`/contao/calendar-ical/import/{id}`) mit anderem Ablauf. Die Templates `be_import_calendar.html5`,
`be_import_calendar_confirmation.html5` und `be_import_calendar_csv_headers.html5` sowie der Backend-Modul-Schlüssel
`import` (`?do=calendar&key=import`) entfallen; haben Sie die Templates überschrieben, werden sie nicht mehr verwendet.
Die globale Operation in `tl_calendar_events` heißt jetzt `import` (vorher `export`). Das Bundle benötigt PHP 8.3 und
läuft mit Contao 5.3 und neueren Versionen.

Wenn Sie den Standardwert für die Differenz zwischen Start- und Enddatum in Ihrer `localconfig.php` über
`$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']` überschrieben haben, müssen Sie diesen Wert jetzt in Ihre
`config.yml` übernehmen:

```yaml
cgoit_contao_calendar_ical:
    end_date_time_difference_in_days: 365
```
