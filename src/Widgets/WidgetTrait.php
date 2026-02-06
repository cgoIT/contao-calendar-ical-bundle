<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c) 2026, cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Widgets;

use Cgoit\ContaoCalendarIcalBundle\Util\TimezoneUtil;
use Contao\CheckBox;
use Contao\Config;
use Contao\Date;
use Contao\FileTree;
use Contao\Image;
use Contao\Input;
use Contao\SelectMenu;
use Contao\StringUtil;
use Contao\TextField;
use Contao\Widget;

trait WidgetTrait
{
    private bool $blnSave = true;

    private TimezoneUtil $timezoneUtil;

    public function setTimezoneUtil(TimezoneUtil $timezoneUtil): void
    {
        $this->timezoneUtil = $timezoneUtil;
    }

    /**
     * Return the delete calendar widget as object.
     */
    private function getDeleteWidget(mixed $value = null): CheckBox
    {
        $widget = new CheckBox();

        $widget->id = 'deleteCalendar';
        $widget->name = 'deleteCalendar';
        $widget->mandatory = false;
        $widget->options = [
            [
                'value' => '1',
                'label' => $GLOBALS['TL_LANG']['tl_calendar_events']['importDeleteCalendar'][0],
            ],
        ];
        $widget->value = $value;

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['importDeleteCalendar'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importDeleteCalendar'][1];
        }

        // Valiate input
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the correct timezone widget as object.
     */
    private function getCorrectTimezoneWidget(mixed $value = null): CheckBox
    {
        $widget = new CheckBox();

        $widget->id = 'correctTimezone';
        $widget->name = 'correctTimezone';
        $widget->value = $value;
        $widget->options = [
            [
                'value' => 1,
                'label' => $GLOBALS['TL_LANG']['tl_calendar_events']['correctTimezone'][0],
            ],
        ];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['correctTimezone'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['correctTimezone'][1];
        }

        // Valiate input
        if ('tl_import_calendar_confirmation' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the status widget as object.
     */
    private function getTimezoneWidget(mixed $value = null): SelectMenu
    {
        $widget = new SelectMenu();

        $widget->id = 'timezone';
        $widget->name = 'timezone';
        $widget->mandatory = true;
        $widget->value = $value;

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['timezone'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['timezone'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['timezone'][1];
        }

        $arrOptions = $this->timezoneUtil->getTimezones();

        foreach (\DateTimeZone::listIdentifiers() as $identifier) {
            $parts = explode('/', $identifier, 2);
            if (!\array_key_exists($parts[0], $arrOptions)) {
                $arrOptions[$parts[0]] = [];
            }
            $arrOptions[$parts[0]][] = ['value' => $identifier, 'label' => $identifier];
        }

        $widget->options = $arrOptions;

        // Valiate input
        if ('tl_import_calendar_confirmation' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the filter widget as object.
     */
    private function getFilterWidget(mixed $value = ''): TextField
    {
        $widget = new TextField();

        $widget->id = 'filterEventTitle';
        $widget->name = 'filterEventTitle';
        $widget->mandatory = false;
        $widget->maxlength = 50;
        $widget->rgxp = 'text'; // @phpstan-ignore-line
        $widget->value = $value;

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['importFilterEventTitle'][0];

        if (
            $GLOBALS['TL_CONFIG']['showHelp'] && \strlen(
                (string) $GLOBALS['TL_LANG']['tl_calendar_events']['importFilterEventTitle'][1],
            )
        ) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importFilterEventTitle'][1];
        }

        // Valiate input
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the file tree widget as object.
     */
    private function getFileTreeWidget(mixed $value = null): FileTree
    {
        $widget = new FileTree();

        $widget->id = 'icssource';
        $widget->name = 'icssource';
        $widget->strTable = 'tl_calendar_events';
        $widget->strField = 'icssource';
        $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['icssource']['eval']['fieldType'] = 'radio';
        $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['icssource']['eval']['files'] = true;
        $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['icssource']['eval']['filesOnly'] = true;
        $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['icssource']['eval']['extensions'] = 'ics,csv';
        $widget->value = $value;

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['icssource'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['icssource'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['icssource'][1];
        }

        // Valiate input
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the start date widget as object.
     */
    private function getStartDateWidget(mixed $value = null): TextField
    {
        $widget = new TextField();

        $widget->id = 'startDate';
        $widget->name = 'startDate';
        $widget->mandatory = true;
        $widget->required = true;
        $widget->maxlength = 10;
        $widget->rgxp = 'date'; // @phpstan-ignore-line
        $widget->wizard = $this->getDatePickerWizard($widget);
        $widget->value = $value;

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['importStartDate'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['importStartDate'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importStartDate'][1];
        }

        // Valiate input
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the end date widget as object.
     */
    private function getEndDateWidget(mixed $value = null): TextField
    {
        $widget = new TextField();

        $widget->id = 'endDate';
        $widget->name = 'endDate';
        $widget->mandatory = false;
        $widget->maxlength = 10;
        $widget->rgxp = 'date'; // @phpstan-ignore-line
        $widget->wizard = $this->getDatePickerWizard($widget);
        $widget->value = $value;

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['importEndDate'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['importEndDate'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importEndDate'][1];
        }

        // Valiate input
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * Return the time shift widget as object.
     */
    private function getTimeShiftWidget(mixed $value = 0): TextField
    {
        $widget = new TextField();

        $widget->id = 'timeshift';
        $widget->name = 'timeshift';
        $widget->mandatory = false;
        $widget->maxlength = 4;
        $widget->rgxp = 'digit'; // @phpstan-ignore-line
        $widget->value = $value;

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['importTimeShift'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && \strlen((string) $GLOBALS['TL_LANG']['tl_calendar_events']['importTimeShift'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importTimeShift'][1];
        }

        // Valiate input
        if ('tl_import_calendar' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    private function getDateFormatWidget(string|null $value = null): TextField
    {
        $widget = new TextField();

        $widget->id = 'dateFormat';
        $widget->name = 'dateFormat';
        $widget->mandatory = true;
        $widget->required = true;
        $widget->maxlength = 20;
        $widget->value = !empty($value) ? $value : $GLOBALS['TL_LANG']['tl_calendar_events']['dateFormat'];

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['importDateFormat'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && !empty((string) $GLOBALS['TL_LANG']['tl_calendar_events']['importDateFormat'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importDateFormat'][1];
        }

        // Valiate input
        if ('tl_csv_headers' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    private function getTimeFormatWidget(string|null $value = null): TextField
    {
        $widget = new TextField();

        $widget->id = 'timeFormat';
        $widget->name = 'timeFormat';
        $widget->mandatory = true;
        $widget->required = true;
        $widget->maxlength = 20;
        $widget->value = !empty($value) ? $value : $GLOBALS['TL_LANG']['tl_calendar_events']['timeFormat'];

        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['importTimeFormat'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && !empty((string) $GLOBALS['TL_LANG']['tl_calendar_events']['importTimeFormat'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['importTimeFormat'][1];
        }

        // Valiate input
        if ('tl_csv_headers' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    private function getEncodingWidget(string|null $value = null): SelectMenu
    {
        $widget = new SelectMenu();

        $widget->id = 'encoding';
        $widget->name = 'encoding';
        $widget->mandatory = true;
        $widget->value = $value;
        $widget->label = $GLOBALS['TL_LANG']['tl_calendar_events']['encoding'][0];

        if ($GLOBALS['TL_CONFIG']['showHelp'] && !empty((string) $GLOBALS['TL_LANG']['tl_calendar_events']['encoding'][1])) {
            // TODO prüfen, ob der Text hin muss @phpstan-ignore-next-line
            $widget->help = $GLOBALS['TL_LANG']['tl_calendar_events']['encoding'][1];
        }

        $arrOptions = [
            ['value' => 'utf8', 'label' => 'UTF-8'],
            ['value' => 'latin1', 'label' => 'ISO-8859-1 (Windows)'],
        ];
        $widget->options = $arrOptions;

        // Valiate input
        if ('tl_csv_headers' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    /**
     * @param array<mixed> $fieldvalues
     */
    private function getFieldSelector(int $index, string $name, array $fieldvalues, mixed $value = null): SelectMenu
    {
        $widget = new SelectMenu();

        $widget->id = $name.'['.$index.']';
        $widget->name = $name.'['.$index.']';
        $widget->mandatory = false;
        $widget->value = $value;
        $widget->label = 'csvfield';

        $arrOptions = [];

        $arrOptions[] = ['value' => '', 'label' => '-'];

        foreach ($fieldvalues as $fieldvalue) {
            if (\is_array($fieldvalue)) {
                $arrOptions[] = ['value' => $fieldvalue[0], 'label' => $fieldvalue[1]];
            } else {
                $arrOptions[] = ['value' => $fieldvalue, 'label' => $fieldvalue];
            }
        }

        $widget->options = $arrOptions;

        // Valiate input
        if ('tl_csv_headers' === Input::post('FORM_SUBMIT')) {
            $widget->validate();

            if ($widget->hasErrors()) {
                echo 'field';
                $this->blnSave = false;
            }
        }

        return $widget;
    }

    private function getDatePickerWizard(Widget $objWidget): string
    {
        return Image::getHtml('assets/datepicker/images/icon.svg', '', 'title="'.StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['datepicker']).'" id="toggle_'.$objWidget->id.'" style="cursor:pointer"').'
  <script>
    window.addEvent("domready", function() {
      new Picker.Date($("ctrl_'.$objWidget->id.'"), {
        draggable: false,
        toggle: $("toggle_'.$objWidget->id.'"),
        format: "'.Date::formatToJs(Config::get('dateFormat')).'",
        positionOffset: {x:-211,y:-209},
        pickerClass: "datepicker_bootstrap",
        useFadeInOut: !Browser.ie,
        startDay: '.$GLOBALS['TL_LANG']['MSC']['weekOffset'].',
        titleFormat: "'.$GLOBALS['TL_LANG']['MSC']['titleFormat'].'"
      });
    });
  </script>';
    }
}
