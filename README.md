[![Latest Version on Packagist](http://img.shields.io/packagist/v/cgoit/contao-calendar-ical-php8-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-php8-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FcgoIT%2Fcontao-calendar-ical-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/cgoit/contao-calendar-ical-php8-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-php8-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/cgoit/contao-calendar-ical-php8-bundle.svg?style=flat)](https://packagist.org/packages/cgoit/contao-calendar-ical-php8-bundle)

Contao 4 and Contao 5 Calendar iCal Bundle
=======================

iCal support for calendar of Contao OpenSource CMS. Forked from https://github.com/Craffft/contao-calendar-ical-bundle. PHP-8 ready.

Installation
------------

### Step 1: Download the Bundle

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

Starting with version 5 of this bundle Contao 5 is supported. Many things have been refactored in this version, many classes have been split or moved. Therefore, the chance that something does not yet work 100% is quite high. I therefore recommend that all Contao 4 users check very carefully whether version 5 works the way they want it to. Alternatively, version 4 can continue to be used for Contao 4.

#### Important

If you have overwritten the default difference between start and end date in your `localconfig.php` via setting a value for `$GLOBALS['calendar_ical']['endDateTimeDifferenceInDays']` you have to put this value now into your `config.yml`.

```
cgoit_contao_calendar_ical:
    end_date_time_difference_in_days: 365
```
