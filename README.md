[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcontao-calendar-ical-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)

Contao 5 Calendar iCal Bundle
=============================

iCal-Unterstützung für den Kalender von Contao Open Source CMS. Fork von https://github.com/Craffft/contao-calendar-ical-bundle. PHP-8-ready.

Installation
------------

Öffnen Sie eine Konsole, wechseln Sie in Ihr Projektverzeichnis und führen Sie den folgenden Befehl aus, um die
neueste stabile Version des Bundles zu installieren:

```bash
$ composer require cgoit/contao-calendar-ical-bundle
```

Dafür muss Composer global installiert sein, wie im
[Installationskapitel](https://getcomposer.org/doc/00-intro.md) der Composer-Dokumentation beschrieben.

Unterstützte Contao-Versionen
-----------------------------

Das Bundle unterstützt Contao 5 (ab 5.3) und Symfony 6.4 bzw. 7.3 oder neuer. Contao 4.13 und Symfony 5.4 werden nicht
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
bleiben unberührt. Alternativ liefert eine Seite vom Typ `ics_feed` oder das Inhaltselement „iCal“ die Termine
ausgewählter Kalender. Ein dort eingetragenes Präfix wird dem Titel jedes Termins vorangestellt; ohne Angabe gilt das
Präfix des jeweiligen Kalenders.

Geschützte Kalender
-------------------

Geschützte Kalender (`protected` mit Mitgliedergruppen) werden nur an Frontend-Mitglieder der erlaubten Gruppen
exportiert. Das gilt für den Export einzelner Termine, den Eventlisten-Export, die Seite vom Typ `ics_feed` und das
Inhaltselement „iCal“. Unveröffentlichte Termine werden nie exportiert.

Kalender-Apps melden sich nicht als Contao-Mitglied an. Ein Feed mit geschützten Kalendern enthält für sie daher keine
Termine dieser Kalender; er wird zudem nie in einem gemeinsamen Cache abgelegt.

Die Option „iCal Abonnement erstellen“ schreibt eine **öffentliche** Datei nach `share/`. Sie steht daher für
geschützte Kalender nicht zur Verfügung; verwenden Sie stattdessen eine Seite vom Typ `ics_feed`.

Kalender aus einer Web-Quelle importieren
-----------------------------------------

Ein Kalender kann seine Termine regelmäßig aus einer ICS-URL übernehmen („Web-Quelle“).

Ab Version 6.0 werden Web-Quellen **nicht mehr beim Seitenaufruf**, sondern nur noch im Hintergrund aktualisiert: Der
Contao-Cron (stündlich) importiert jeden Kalender, dessen Cache-Dauer („Cache-Dauer in Sekunden“, Standard 86400)
abgelaufen ist. Der Contao-Cron muss daher eingerichtet sein (Systemcron oder Poor-Man's-Cron). Beim Speichern des
Kalenders im Backend wird die Quelle sofort abgerufen, damit geänderte Einstellungen (URL, Filter, Zeitzone, ...) gleich
wirksam werden. Ein fehlgeschlagener Abruf wird beim nächsten Cron-Lauf erneut versucht.

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

Importierte Inhalte
-------------------

Titel, Ort, Beschreibung, Teilnehmer und Kontakte aus ICS- und CSV-Dateien werden beim Import kodiert gespeichert
(HTML wird als Text angezeigt, Insert-Tags werden entfernt). Das gilt für den Web-Import wie für den Import im Backend.
Bereits importierte Termine werden beim nächsten Import entsprechend aktualisiert.

Datumswerte ohne Uhrzeit (ganztägige Termine) und Zeiten ohne Zeitzone („floating time“) werden in der Zeitzone des
Kalenders gelesen (Web-Quelle: Einstellung „Zeitzone“; sonst Zeitzone der ICS-Datei bzw. des Systems). Zeiten in UTC oder
mit `TZID` behalten ihre Zeitzone.

Der reguläre Ausdruck für die Titel-Umschreibung wird beim Speichern des Kalenders geprüft und benötigt Trennzeichen,
z. B. `/^\[Verein\] /`. Das Feld „Benutzername:Passwort“ für den Proxy wird verdeckt angezeigt und ist nicht
durchsuchbar. Hochgeladene Dateien für den Backend-Import werden nach dem Import aus `system/tmp` gelöscht.

Wichtig
-------

Wenn Sie den Standardwert für die Differenz zwischen Start- und Enddatum in Ihrer `localconfig.php` über
`$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']` überschrieben haben, müssen Sie diesen Wert jetzt in Ihre
`config.yml` übernehmen:

```yaml
cgoit_contao_calendar_ical:
    end_date_time_difference_in_days: 365
```
