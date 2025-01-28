[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcontao-calendar-ical-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/contao-calendar-ical-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-bundle)

Contao 4 and Contao 5 Calendar iCal Bundle
=======================

iCal support for calendar of Contao OpenSource CMS. Forked from https://github.com/Craffft/contao-calendar-ical-bundle. PHP-8 ready.

Installation
------------

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require cgoit/contao-calendar-ical-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Contao 5 support
----------------

Starting with version 5 of this bundle Contao 5 is supported.

Export single events as ics
---------------------------

To export a single event as ics (e.g. to give a user the ability to import it into his own calendar) you can add a link to the url
`/_event/ics-export/{id}` where `id` is the id of the event you want to export. You can also use the route name `event_frontend_ics_export`
in combination with the parameter `id` for that.

For example you can add the following snippet to your `event_full.html5` template.

```html
<a href="<?= $this->route('event_frontend_ics_export', ['id' => $this->id]) ?>" class="ics-export" rel="nofollow" title="Export event as ics">
  <img src="/bundles/cgoitcontaocalendarical/ics.svg" width="32" height="32" alt="">
</a>
```

Important
---------

If you have overwritten the default difference between start and end date in your `localconfig.php` via setting a value for `$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']` you have to put this value now into your `config.yml`.

```
cgoit_contao_calendar_ical:
    end_date_time_difference_in_days: 365
```
