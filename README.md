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

Geschützte Kalender
-------------------

Geschützte Kalender (`protected` mit Mitgliedergruppen) werden nur an Frontend-Mitglieder der erlaubten Gruppen
exportiert. Das gilt für den Export einzelner Termine, den Eventlisten-Export, die Seite vom Typ `ics_feed` und das
Inhaltselement „iCal“. Unveröffentlichte Termine werden nie exportiert.

Die Option „iCal Abonnement erstellen“ schreibt eine **öffentliche** Datei nach `share/`. Sie steht daher für
geschützte Kalender nicht zur Verfügung; verwenden Sie stattdessen eine Seite vom Typ `ics_feed`.

Wichtig
-------

Wenn Sie den Standardwert für die Differenz zwischen Start- und Enddatum in Ihrer `localconfig.php` über
`$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']` überschrieben haben, müssen Sie diesen Wert jetzt in Ihre
`config.yml` übernehmen:

```yaml
cgoit_contao_calendar_ical:
    end_date_time_difference_in_days: 365
```
