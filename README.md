# Contao Calendar iCal Bundle

[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcontao-calendar-ical-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)

## Inhaltsverzeichnis

- [Kurzüberblick](#kurzüberblick)
- [Installation](#installation)
- [Update auf Version 6](#update-auf-version-6)
- [Schnellstart](#schnellstart)
- [Export](#export)
  - [Einzelne Termine](#einzelne-termine-als-ics-exportieren)
  - [Eventlisten](#eventlisten-als-ics-exportieren)
  - [Abonnement-Dateien](#abonnement-dateien)
  - [Feed-Seite und Download-Element](#feed-seite-und-download-element)
  - [Geschützte Kalender](#geschützte-kalender)
  - [Was beim Export aus einem Termin wird](#was-beim-export-aus-einem-termin-wird)
- [Import](#import)
  - [Import im Backend (ICS und CSV)](#import-im-backend-ics-und-csv)
  - [Import aus einer Web-Quelle](#import-aus-einer-web-quelle)
  - [CSV-Import](#csv-import)
  - [Importierte Inhalte](#importierte-inhalte)
  - [Was beim Import aus einem Feed wird](#was-beim-import-aus-einem-feed-wird)
- [Zusammenspiel mit dem Calendar Extended Bundle](#zusammenspiel-mit-dem-calendar-extended-bundle)
- [Konfiguration](#konfiguration)
- [Erweiterbarkeit](#erweiterbarkeit)
- [Mitwirken](#mitwirken)
- [Lizenz](#lizenz)

## Kurzüberblick

Das **Contao Calendar iCal Bundle** ergänzt den Kalender von Contao (`contao/calendar-bundle`) um den Austausch mit
anderen Kalenderprogrammen im ICS-Format. Es ist ein Fork von
[craffft/contao-calendar-ical-bundle](https://github.com/Craffft/contao-calendar-ical-bundle).

- **Export**: einzelne Termine, Eventlisten, ganze Kalender als Abonnement-Datei, Feed-Seite oder Download-Element
- **Import im Backend**: ICS- und CSV-Dateien mit Zeitraum, Zeitzonenprüfung und Spaltenzuordnung
- **Import aus einer Web-Quelle**: ein Kalender übernimmt seine Termine regelmäßig aus einer ICS-URL
- **Wiederholungen und Ausnahmen** werden in beide Richtungen abgebildet, mit dem
  [Calendar Extended Bundle](#zusammenspiel-mit-dem-calendar-extended-bundle) auch erweiterte Wiederholungen,
  ausgeblendete und verschobene Termine
- **Erweiterungspunkte** über Symfony-Events für Import und Export

Der Contao-Kalender bleibt die Quelle der Wahrheit für Termine. Das Bundle legt keine eigenen Tabellen an, sondern
ergänzt `tl_calendar`, `tl_calendar_events`, `tl_content` und `tl_page` um Felder mit dem Präfix `ical_`.

## Installation

Das Bundle kann über den **Contao Manager** oder per **Composer** installiert werden:

```bash
composer require cgoit/contao-calendar-ical-bundle
```

Anschließend müssen die Datenbankänderungen übernommen werden, entweder über den Contao Manager oder auf der
Kommandozeile:

```bash
bin/console contao:migrate
```

Voraussetzungen:

| Komponente | Version |
|------------|---------|
| PHP | 8.3 oder neuer |
| Contao | 5.3 oder neuer |
| Symfony | 6.4 oder 7.3 oder neuer |

Contao 4.13 und Symfony 5.4 werden nicht mehr unterstützt. Für Contao 4 muss eine ältere Version des Bundles
verwendet werden.

## Update auf Version 6

Version 6.0 bringt einige Änderungen mit, die Anpassungen im Projekt erfordern können:

| Thema | Beschreibung |
|-------|--------------|
| Import im Backend | Der Import ist eine neue Seite (`/contao/calendar-ical/import/{id}`) mit anderem Ablauf. Die Templates `be_import_calendar.html5`, `be_import_calendar_confirmation.html5` und `be_import_calendar_csv_headers.html5` sowie der Backend-Modul-Schlüssel `import` (`?do=calendar&key=import`) entfallen. Überschriebene Templates werden nicht mehr verwendet. |
| Operation in der Terminliste | Die globale Operation in `tl_calendar_events` heißt jetzt `import` (vorher `export`). |
| Web-Quelle | Quellen werden nicht mehr beim Seitenaufruf, sondern nur noch im Hintergrund aktualisiert. Der Contao-Cron muss eingerichtet sein (siehe [Import aus einer Web-Quelle](#import-aus-einer-web-quelle)). |
| TLS-Prüfung | Das Zertifikat der Gegenstelle wird geprüft. Quellen mit selbstsigniertem oder abgelaufenem Zertifikat lassen sich nicht mehr importieren. |
| `ext-curl` | Wird nicht mehr benötigt, stattdessen wird `symfony/http-client` verwendet. |
| Content-Element „iCal Download“ | Wird mit `content_element/ical.html.twig` gerendert (statt `ce_ical.html5`). Wer `ce_ical` überschrieben hatte, legt stattdessen ein Twig-Template an. Das Feld „Template für iCal Download-Element“ (`ical_download_template`, `ce_download_*`) entfällt; das Markup des Links steht im Twig-Template. |
| Datumsfelder | Start- und Enddatum von Abonnement und Web-Quelle (`ical_start`, `ical_end`, `ical_source_start`, `ical_source_end`) werden als Zahl statt als Text gespeichert. `contao:migrate` wandelt die Spalten um, leere Werte werden zu `NULL`. |
| Konfiguration | Der Wert `$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']` aus der `localconfig.php` wird nicht mehr gelesen, siehe [Konfiguration](#konfiguration). |

Nach dem Update `bin/console contao:migrate` ausführen und die Import-Einstellungen der Kalender mit Web-Quelle
prüfen.

## Schnellstart

**Termine veröffentlichen:**

1. Im Kalender die Option **iCal Abonnement erstellen** aktivieren und speichern.
2. Die Datei `share/<Alias>.ics` (ohne Alias: `share/calendar<ID>.ics`) in Kalender-Apps als Abonnement eintragen.

**Termine übernehmen:**

1. In der Terminliste eines Kalenders den **Import** öffnen (Schaltfläche „Kalender importieren“, in neueren
   Contao-Versionen im Menü „…“).
2. Eine ICS- oder CSV-Datei wählen, Zeitraum und Optionen festlegen und den Anweisungen folgen.

Für einen regelmäßigen Abgleich mit einem fremden Kalender stattdessen im Kalender eine
[Web-Quelle](#import-aus-einer-web-quelle) eintragen.

## Export

Alle Export-Wege nutzen dieselbe Logik. Wie aus einem Termin ein ICS-Eintrag wird, steht in der
[Übersicht](#was-beim-export-aus-einem-termin-wird).

| Weg | Geeignet für |
|-----|--------------|
| [Einzeltermin](#einzelne-termine-als-ics-exportieren) | Besucher übernehmen einen Termin in den eigenen Kalender |
| [Eventliste](#eventlisten-als-ics-exportieren) | Besucher übernehmen alle Termine einer Liste |
| [Abonnement-Datei](#abonnement-dateien) | Öffentlicher Kalender, der in Kalender-Apps abonniert wird |
| [Seite `ics_feed`](#feed-seite-und-download-element) | Feed aus mehreren Kalendern, auch mit geschützten Kalendern |
| [Content-Element „iCal“](#feed-seite-und-download-element) | Download-Link für ausgewählte Kalender |

### Einzelne Termine als ICS exportieren

Um einen einzelnen Termin als ICS zu exportieren (z. B. damit Besucher ihn in den eigenen Kalender importieren
können), wird auf die URL `/_event/ics-export/{id}` verlinkt, wobei `id` die ID des Termins ist. Alternativ kann der
Routenname `event_frontend_ics_export` mit dem Parameter `id` verwendet werden.

Zum Beispiel lässt sich das folgende Snippet in das Template `event_full.html5` einfügen:

```html
<a href="<?= $this->route('event_frontend_ics_export', ['id' => $this->id]) ?>" class="ics-export" rel="nofollow" title="Termin als ICS exportieren">
    <img src="/bundles/cgoitcontaocalendarical/ics.svg" width="32" height="32" alt="">
</a>
```

### Eventlisten als ICS exportieren

Um eine Eventliste (Modul oder Content-Element) zu exportieren, wird auf die URL `/_eventlist/ics-export/{id}`
verlinkt, wobei `id` die ID des Eventlisten-Moduls ist. Alternativ kann der Routenname
`eventlist_frontend_ics_export` mit dem Parameter `id` verwendet werden.

Zum Beispiel lässt sich das folgende Snippet in das Template `mod_eventlist.html5` einfügen:

```html
<a href="<?= $this->route('eventlist_frontend_ics_export', ['id' => $this->id]) ?>" class="ics-export" rel="nofollow" title="Als ICS exportieren">
  <img src="/bundles/cgoitcontaocalendarical/ics.svg" width="32" height="32" alt="">
</a>
```

Eine Eventliste wird nur exportiert, wenn sie für den Besucher sichtbar ist. Bei Inhaltselementen gilt das auch für
den Artikel, die Seite und übergeordnete Elemente (veröffentlicht, Zugriffsschutz). Hooks anderer Erweiterungen
(z. B. `getAllEvents` des Calendar Extended Bundles) werden berücksichtigt.

> 💡 **Hinweis**
>
> Die Routennamen und Pfade der Export-Routen sowie die Icons `ics.svg` und `ics--dark.svg` unter
> `/bundles/cgoitcontaocalendarical/` sind öffentliche API und ändern sich nur mit einem Hinweis auf einen Breaking
> Change.

### Abonnement-Dateien

Mit der Option **iCal Abonnement erstellen** schreibt das Bundle die Datei `share/<Alias>.ics` (ohne Alias:
`share/calendar<ID>.ics`). Beim Neuaufbau werden nur diese Dateien ersetzt oder gelöscht, andere Dateien in `share/`
bleiben unberührt.

Die Datei wird neu erzeugt

- nach dem Speichern, Kopieren, Löschen, Wiederherstellen und Veröffentlichen von Terminen (einmal pro Anfrage, nach
  dem Senden der Antwort),
- beim Speichern des Kalenders,
- täglich per Cron,
- über die Systemwartung.

> 💡 **Hinweis**
>
> Beginnt oder endet die Veröffentlichung eines Termins zu einer bestimmten Zeit, wirkt sich das erst beim nächsten
> Neuaufbau aus.

### Feed-Seite und Download-Element

Alternativ zur Abonnement-Datei liefern

- eine Seite vom Typ **`ics_feed`** (URL-Suffix `.ics`) und
- das Content-Element **„iCal“** (Download-Link, die Datei wird bei `?ical=<ID des Elements>` ausgeliefert)

die Termine ausgewählter Kalender. Ein dort eingetragenes Präfix wird dem Titel jedes Termins vorangestellt; ohne
Angabe gilt das Präfix des jeweiligen Kalenders. Das Markup des Download-Links steht im Twig-Template
`content_element/ical.html.twig`.

### Geschützte Kalender

Geschützte Kalender (`protected` mit Mitgliedergruppen) werden nur an Frontend-Mitglieder der erlaubten Gruppen
exportiert. Das gilt für den Export einzelner Termine, den Eventlisten-Export, die Seite vom Typ `ics_feed` und das
Content-Element „iCal“. Unveröffentlichte Termine werden nie exportiert.

Kalender-Apps melden sich nicht als Contao-Mitglied an. Ein Feed mit geschützten Kalendern enthält für sie daher
keine Termine dieser Kalender; er wird zudem nie in einem gemeinsamen Cache abgelegt.

> ⚠️ **Wichtig**
>
> Die Option „iCal Abonnement erstellen“ schreibt eine **öffentliche** Datei nach `share/`. Sie steht daher für
> geschützte Kalender nicht zur Verfügung. Stattdessen eine Seite vom Typ `ics_feed` verwenden.

### Was beim Export aus einem Termin wird

Die Übersicht gilt für alle Wege (Einzeltermin, Eventliste, Abo-Datei, Feed-Seite, Download-Element). „Mit
Erweiterung“ meint das [`cgoit/calendar-extended-bundle`](https://github.com/cgoIT/calendar-extended-bundle).

| Im Termin | Ohne Erweiterung | Mit Erweiterung |
|---|---|---|
| Termin mit Uhrzeit | `DTSTART`/`DTEND` mit Datum und Uhrzeit in der Zeitzone des Systems; ohne Ende eine Stunde | wie ohne |
| Ganztägiger Termin | `DTSTART`/`DTEND` als Datum; das Ende ist exklusiv (der Tag nach dem letzten Tag); ohne Ende ein Tag | wie ohne |
| Termin ohne Startdatum | Wird nicht exportiert | wie ohne |
| Titel | `SUMMARY`, mit dem Präfix des Kalenders bzw. der Seite/des Elements | wie ohne |
| Teaser | `DESCRIPTION` (HTML entfernt, Insert-Tags aufgelöst) | wie ohne |
| Ort (`location`) | `LOCATION`, falls das Feld vorhanden ist | wie ohne |
| Teilnehmer, Kontakt | `ATTENDEE` (nur gültige E-Mail-Adressen) und `CONTACT`, falls die Felder vorhanden sind | wie ohne |
| Einfache Wiederholung | `RRULE` mit Einheit (täglich bis jährlich) und `INTERVAL` | wie ohne |
| Erweiterte Wiederholung („erster Donnerstag“) | Nicht verfügbar | `RRULE` monatlich mit `BYDAY` (z. B. `1TH`) |
| Wiederholung mit Ende | `COUNT` (Anzahl der Termine einschließlich des ersten) | wie ohne |
| Ausnahme „Termin nicht anzeigen“ (einzeln oder für einen Zeitraum) | Nicht verfügbar | `EXDATE` (als Datum oder Datum mit Uhrzeit, passend zum Termin) |
| Ausnahme „Termin verschieben“ | Nicht verfügbar | Zusätzlicher Termin mit gleicher `UID` und `RECURRENCE-ID` der ursprünglichen Wiederholung; Kalender-Apps ersetzen damit genau diese eine Wiederholung |
| Unveröffentlichter Termin, Termin außerhalb des Zeitraums | Wird nicht exportiert | wie ohne |
| Termin eines geschützten Kalenders | Nur für berechtigte Mitglieder, nie in Abo-Dateien | wie ohne |
| Termin mit mehreren Einträgen in einer Eventliste | Wird nur einmal exportiert; die `RRULE` trägt die Wiederholungen | wie ohne |

Als `ATTENDEE` kann ICS nur E-Mail-Adressen aufnehmen. Teilnehmer ohne gültige E-Mail-Adresse werden daher nicht
exportiert.

## Import

Termine lassen sich auf zwei Wegen übernehmen: einmalig im Backend (ICS oder CSV) oder regelmäßig aus einer
Web-Quelle. Beide Wege nutzen dieselbe Abbildung auf das Contao-Modell, siehe
[Was beim Import aus einem Feed wird](#was-beim-import-aus-einem-feed-wird). Importierte Termine werden über die
`UID` des ICS-Eintrags (`ical_uuid`) wiedererkannt und beim erneuten Import aktualisiert.

### Import im Backend (ICS und CSV)

In der Terminliste eines Kalenders öffnet die Schaltfläche „Kalender importieren“ (Menü „…“ in neueren
Contao-Versionen) den Import. Er steht Benutzern zur Verfügung, die das Modul „Events“ nutzen und den Kalender
bearbeiten dürfen.

1. **Datei und Einstellungen:** Wählen Sie eine ICS- oder CSV-Datei, den Zeitraum (Startdatum und Enddatum; ohne
   Enddatum gilt der 31.12. des übernächsten Jahres, bei ICS und CSV gleichermaßen), eine manuelle Zeitverschiebung
   in Stunden, ob die bestehenden Termine des Kalenders vorher entfernt werden sollen, und optional einen
   **Termin-Titel-Filter**: Nur Termine, deren Titel diese Zeichenkette enthält (ohne Beachtung der
   Groß-/Kleinschreibung), werden importiert.
2. **ICS – Zeitzone:** Unterscheidet sich die Zeitzone der Datei von der Zeitzone des Systems, oder enthält die Datei
   keine, fragt der Import nach. Sie können die Zeitzone der Datei durch die des Systems ersetzen („Zeitzone
   korrigieren“) bzw. eine Zeitzone für die Termine wählen.
3. **CSV – Spalten zuordnen:** Jede Spalte der Datei wird einem Feld des Termins zugeordnet (Titel, Start/Ende,
   Beschreibung, Ort, ...). Spalten, deren Name einem Feld entspricht, sind vorbelegt. Zeichencodierung, Datums- und
   Zeitformat sind einstellbar; „Überprüfen“ liest die Vorschau mit den geänderten Einstellungen neu ein.

Die hochgeladene Datei bleibt nur bis zum Ende des Imports (oder einer Stunde) in `system/tmp` liegen. „Abbrechen“
verwirft sie sofort. Werden bei „Bestehende Termine entfernen“ keine Termine gelesen (leere oder unlesbare Datei,
keine Spalte zugeordnet), bleibt der Kalender unverändert.

### Import aus einer Web-Quelle

Ein Kalender kann seine Termine regelmäßig aus einer ICS-URL übernehmen („Web-Quelle“). Die Einstellungen stehen im
Kalender; zusätzlich zu URL, Zeitraum und Zeitzone gibt es:

| Einstellung | Beschreibung |
|-------------|--------------|
| **Termin-Titel filtern** (`ical_filter_event_title`) | Nur Termine, deren Titel die Zeichenkette enthält, werden importiert. |
| **Termin-Titel Pattern** (`ical_pattern_event_title`) und **Replacement** (`ical_replacement_event_title`) | Schreiben den Titel mit einem regulären Ausdruck um. Der Ausdruck wird beim Speichern des Kalenders geprüft und benötigt Trennzeichen, z. B. `/^\[Verein\] /`. |
| **Cache-Dauer in Sekunden** | Nach dieser Zeit wird die Quelle erneut abgerufen (Standard 86400). |
| **Proxy** | Optionaler Proxy, falls der Server nur darüber ins Internet kommt. Das Feld „Benutzername:Passwort“ wird verdeckt angezeigt und ist nicht durchsuchbar. |

#### Ablauf

Web-Quellen werden **nicht beim Seitenaufruf**, sondern im Hintergrund aktualisiert: Der Contao-Cron (stündlich)
importiert jeden Kalender, dessen Cache-Dauer abgelaufen ist. Der Contao-Cron muss daher eingerichtet sein
(Systemcron oder Poor-Man's-Cron). Beim Speichern des Kalenders im Backend wird die Quelle sofort abgerufen, damit
geänderte Einstellungen (URL, Filter, Zeitzone, ...) gleich wirksam werden. Hat der Kalender ein Abonnement, wird
auch dessen Datei neu erzeugt.

Der Import ist gegen Fehler abgesichert:

- Ein Import läuft nie doppelt (Sperre in der Datenbank, eine verwaiste Sperre verfällt nach zwei Minuten).
- Die Termine des Kalenders werden in einer Transaktion ersetzt: Bei einem Fehler bleiben die bisherigen Termine
  erhalten.
- Enthält die Quelle überhaupt keine Termine (z. B. eine leere oder fehlerhafte Antwort des Servers), bleiben die
  vorhandenen Termine ebenfalls erhalten. Der Abruf wird als Warnung protokolliert.
- Ein fehlgeschlagener Abruf wird beim nächsten Cron-Lauf erneut versucht. Der Zeitpunkt der letzten erfolgreichen
  Synchronisation (`ical_last_sync`) wird nur nach einem erfolgreichen Import gesetzt.

#### Absicherung des Downloads

- Erlaubt sind nur `http://`, `https://` und `webcal://`. `webcal://` wird immer als `https://` abgerufen; Quellen,
  die nur per HTTP erreichbar sind, müssen mit `http://` eingetragen werden.
- Das TLS-Zertifikat der Gegenstelle wird **geprüft**. Quellen mit selbstsigniertem oder abgelaufenem Zertifikat
  lassen sich nicht importieren; die Gegenstelle muss ein gültiges Zertifikat verwenden.
- Ziele in privaten Netzen (z. B. `127.0.0.1`, `192.168.x.x`, Cloud-Metadaten-Adressen) werden ohne Proxy blockiert.
  Das gilt auch für Weiterleitungen. Ist ein Proxy konfiguriert, löst dieser das Ziel auf; dann findet die Prüfung
  nicht statt.
- Zeitlimit 10 Sekunden je Anfrage (30 Sekunden gesamt), höchstens 5 Weiterleitungen, höchstens 10 MiB Dateigröße.
- Der Inhalt wird direkt verarbeitet; es werden keine temporären Dateien angelegt.

### CSV-Import

Der CSV-Import im Backend erkennt das Trennzeichen (`,` oder `;`) anhand der Kopfzeile, überspringt Leerzeilen und
unterstützt Felder mit Zeilenumbrüchen in Anführungszeichen. Ein UTF-8-BOM wird entfernt. Die Auswahl „ISO-8859-1
(Windows)“ liest Windows-1252 (damit auch z. B. das Euro-Zeichen).

Datum und Uhrzeit werden streng nach dem gewählten Format geprüft. Datensätze mit einem ungültigen Datum oder einer
ungültigen Uhrzeit werden nicht importiert; nach dem Import zeigt eine Meldung die Nummern dieser Datensätze. Der
Import läuft in einer Transaktion: Bei einem Fehler bleibt der Kalender unverändert, auch wenn „Kalender leeren“
gewählt war.

### Importierte Inhalte

**Veröffentlichung.** Ob importierte Termine sofort veröffentlicht werden, steuert die Einstellung „Importierte
Termine veröffentlichen“ am Kalender (Legende „iCal-Einstellungen“, Standard: ja). Sie gilt für ICS-Dateien,
CSV-Dateien und die Web-Quelle. Ohne die Einstellung werden neue Termine unveröffentlicht angelegt; bereits
vorhandene Termine behalten beim erneuten Import ihren Zustand.

**Kodierung.** Titel, Ort, Beschreibung, Teilnehmer und Kontakte aus ICS- und CSV-Dateien werden kodiert gespeichert
(HTML wird als Text angezeigt, Insert-Tags werden entfernt). Das gilt für den Web-Import wie für den Import im
Backend. Bereits importierte Termine werden beim nächsten Import entsprechend aktualisiert.

**Zeitzonen.** Datumswerte ohne Uhrzeit (ganztägige Termine) und Zeiten ohne Zeitzone („floating time“) werden in
der Zeitzone des Kalenders gelesen (Web-Quelle: Einstellung „Zeitzone“; sonst Zeitzone der ICS-Datei bzw. des
Systems). Zeiten in UTC oder mit `TZID` behalten ihre Zeitzone. Die Auswahl der Zeitzonen kommt aus
`Util\TimezoneUtil`; ganztägige und zeitgebundene Termine sowie Sommerzeitwechsel sind die typischen Fehlerquellen
bei Feeds.

**Wiederholungsende.** Bei Wiederholungen gilt `UNTIL` einschließlich der letzten Wiederholung; es wird als
UTC-Zeitpunkt gelesen (bei ganztägigen Terminen und Zeiten ohne Zeitzone als Ortszeit des Kalenders). Eine Serie mit
`COUNT=1` ist ein einzelner Termin. Gibt ein Feed `UNTIL` nur als Datum an, obwohl der Termin eine Uhrzeit hat, gilt
die Wiederholung dieses Tages noch.

### Was beim Import aus einem Feed wird

Die Übersicht gilt für den Import im Backend und für die Web-Quelle. „Mit Erweiterung“ meint das
[`cgoit/calendar-extended-bundle`](https://github.com/cgoIT/calendar-extended-bundle) (bzw. dessen Felder für
erweiterte Wiederholungen und Ausnahmen).

| Im Feed | Ohne Erweiterung | Mit Erweiterung |
|---|---|---|
| Termin mit Uhrzeit (`DTSTART`/`DTEND`) | Termin mit Start und Ende | wie ohne |
| Ganztägiger Termin (`VALUE=DATE`) | Ganztägiger Termin; das Ende (`DTEND`) ist exklusiv, der letzte Tag ist der Tag davor | wie ohne |
| Termin ohne `DTEND` | Termin ohne Ende | wie ohne |
| Zeit in UTC oder mit `TZID` | Behält ihre Zeitzone | wie ohne |
| Datum oder Zeit ohne Zeitzone („floating“) | Ortszeit des Kalenders (Einstellung „Zeitzone“) | wie ohne |
| Termin außerhalb des Zeitraums der Einstellungen | Wird nicht importiert | wie ohne |
| `RRULE` täglich/wöchentlich/monatlich/jährlich, `INTERVAL` | Wiederholung mit Intervall | wie ohne |
| `RRULE` mit `COUNT` | Anzahl der Wiederholungen (`COUNT=1`: Einzeltermin) | wie ohne |
| `RRULE` mit `UNTIL` | Ende einschließlich der letzten Wiederholung | wie ohne |
| `RRULE` ohne `COUNT` und `UNTIL` | Unbegrenzte Wiederholung | wie ohne |
| `RRULE` mit Wochentag und Position (z. B. `BYDAY=1TH`, erster Donnerstag im Monat) | Einfache Wiederholung (das Intervall, aber nicht der Wochentag) | Erweiterte Wiederholung („erster Donnerstag“) |
| `EXDATE` | Wird ignoriert, die Wiederholung bleibt | Ausnahme „Termin nicht anzeigen“ |
| Geänderte Wiederholung (`UID` mit `RECURRENCE-ID`) | Eigener Termin (Kennung `UID#RECURRENCE-ID`); die ursprüngliche Wiederholung bleibt bestehen | Ausnahme „Termin verschieben“ (um Tage; bei anderer Uhrzeit oder Dauer mit den neuen Zeiten) |
| Abgesagte Wiederholung (`RECURRENCE-ID` mit `STATUS:CANCELLED`) | Wird nicht importiert, die Wiederholung bleibt bestehen | Ausnahme „Termin nicht anzeigen“ |
| Geänderte Wiederholung, deren Serie nicht im Zeitraum liegt | Eigener Termin | Eigener Termin |
| `SUMMARY` | Titel (kodiert, optional gefiltert und umgeschrieben) | wie ohne |
| `DESCRIPTION` | Text-Element des Termins (kodiert) | wie ohne |
| `LOCATION` | Zeile „Ort“ im Text-Element, oder das Feld `location`, falls vorhanden | wie ohne |
| `ATTENDEE` (`CN`), `CONTACT` | Werden nicht übernommen, solange die Felder `cep_participants` und `location_contact` fehlen | Teilnehmer und Kontakt |
| `UID` | Merkt sich der Termin (`ical_uuid`); beim nächsten Import wird er aktualisiert | wie ohne |
| Feed ohne jeden Termin (Web-Quelle) | Die vorhandenen Termine bleiben erhalten | wie ohne |

#### Einschränkungen

- Mit der Erweiterung gehen Titel, Ort und weitere Änderungen einer einzelnen Wiederholung verloren (nur
  Verschiebung und Zeiten werden übernommen). Bei ganztägigen Terminen wird zudem eine geänderte Dauer einer
  einzelnen Wiederholung nicht übernommen.
- Ohne Erweiterung kennt Contao keine Ausnahmen; die Serie zeigt deshalb weiterhin den ursprünglichen Termin neben
  dem geänderten.
- Weitere Wiederholungsregeln (mehrere Wochentage, `BYMONTH`, `BYSETPOS`, ...) werden nicht abgebildet.
- Bei `EXDATE` ist ein Wert in UTC ein exakter Zeitpunkt, mit `TZID` gilt dessen Zeitzone, bei Datumswerten und
  Zeiten ohne Zeitzone die Ortszeit des Kalenders.

## Zusammenspiel mit dem Calendar Extended Bundle

Das optionale [`cgoit/calendar-extended-bundle`](https://github.com/cgoIT/calendar-extended-bundle) erweitert den
Contao-Kalender um erweiterte Wiederholungen („jeder erste Donnerstag im Monat“) und Ausnahmen (Termine ausblenden
oder verschieben). Ist es installiert, nutzt dieses Bundle dessen Modell automatisch:

| Richtung | Wirkung |
|----------|---------|
| Export | Erweiterte Wiederholungen werden als `RRULE` mit `BYDAY` exportiert. Ausgeblendete Wiederholungen werden zu `EXDATE`, verschobene zu einem eigenen Termin mit derselben `UID` und `RECURRENCE-ID`. |
| Import | `BYDAY` mit Position, `EXDATE`, geänderte und abgesagte Wiederholungen werden auf erweiterte Wiederholungen und Ausnahmen abgebildet. Wiederholungen und Ausnahmen werden nach dem Import mit dem Service des Calendar Extended Bundles vorberechnet. |
| Eventlisten | `getAllEvents`-Hooks des Calendar Extended Bundles werden beim Eventlisten-Export berücksichtigt. |

Die Tabellen zu [Export](#was-beim-export-aus-einem-termin-wird) und
[Import](#was-beim-import-aus-einem-feed-wird) zeigen die Unterschiede mit und ohne Erweiterung im Detail.

## Konfiguration

Ist für Abonnement oder Web-Quelle kein Enddatum des Zeitraums angegeben, reicht dieser ab heute so viele Tage in die
Zukunft. Der Wert lässt sich in der `config/config.yaml` des Projekts anpassen:

```yaml
cgoit_contao_calendar_ical:
    end_date_time_difference_in_days: 365
```

| Schlüssel | Beschreibung | Standard |
|-----------|--------------|----------|
| `end_date_time_difference_in_days` | Länge des Zeitraums in Tagen ab heute, wenn kein Enddatum angegeben ist | `365` |

> 💡 **Hinweis**
>
> Wurde der Wert früher in der `localconfig.php` über `$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']`
> überschrieben, muss er jetzt in die `config.yaml` übernommen werden (siehe [Update auf Version 6](#update-auf-version-6)).

## Erweiterbarkeit

Das Bundle stellt Symfony-Events bereit (Namespace `Cgoit\ContaoCalendarIcalBundle\Event`), die mit einem
`#[AsEventListener]` abonniert werden können. Die Events sind öffentliche API und werden stabil gehalten.

| Event | Zeitpunkt |
|---|---|
| `BeforeImportItemEvent` | Vor dem Import eines Termins; mit `$event->skip = true` wird der Termin übersprungen, der Termin (`calendarEventModel`) kann angepasst werden. |
| `AfterImportItemEvent` | Nach dem Import eines Termins. |
| `ModifyExportEventsEvent` | Vor dem Export der Termine eines Kalenders; `$event->events` kann gefiltert oder ergänzt werden. |
| `ModifyVeventEvent` | Für jeden exportierten Termin; `$event->vevent` kann angepasst oder ersetzt werden. |

Die Hooks `$GLOBALS['TL_HOOKS']['icalGetAllEvents']` und `$GLOBALS['TL_HOOKS']['icalModifyVevent']` funktionieren
weiterhin, sind aber deprecated und werden mit der nächsten Major-Version entfernt. Sie sollten auf
`ModifyExportEventsEvent` bzw. `ModifyVeventEvent` umgestellt werden.

## Mitwirken

Fehler und Verbesserungsvorschläge bitte als [Issue auf GitHub](https://github.com/cgoIT/contao-calendar-ical-bundle/issues)
melden. Pull Requests sind willkommen. Commit-Nachrichten folgen den
[Conventional Commits](https://www.conventionalcommits.org/de/v1.0.0/), da daraus Changelog und Versionen erzeugt
werden.

Für die Entwicklung:

```bash
composer install
composer run tests      # PHPUnit
composer run coverage   # Testabdeckung (benötigt PCOV), HTML-Report in build/coverage
composer run all        # Code-Style-, Rector- und Qualitätsprüfungen, danach PHPUnit
```

Änderungen an der Import- oder Export-Logik sollten mit einem Testfall abgedeckt sein.

## Lizenz

Das Bundle steht unter der [LGPL-3.0-or-later](LICENSE).
