<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Drivers;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\FileUpload;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;

/**
 * Data container driver of the import form in the back end. It does not read or write a table: it renders the
 * fields of the virtual table "tl_calendar_ical_import" with the widgets of Contao (which validate the input) and
 * collects the valid values.
 */
class DC_IcalImport extends DataContainer
{
    final public const string TABLE = 'tl_calendar_ical_import';

    /**
     * @var array<string, mixed>
     */
    private array $importValues = [];

    private string $step = 'upload';

    private FileUpload|null $uploader = null;

    /**
     * The constructor of the legacy base class is not public, so the data container could not be a service.
     */
    public function __construct(private readonly ContaoFramework $framework)
    {
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $defaults
     */
    public function initialize(int $calendarId, string $palette, array $defaults = []): void
    {
        self::loadDataContainer(self::TABLE);
        $this->framework->getAdapter(System::class)->loadLanguageFile('default');

        $this->intId = $calendarId;
        $this->strTable = self::TABLE;
        $this->step = $palette;
        $this->importValues = $defaults;
        $this->noReload = false;
    }

    #[\Override]
    public function getPalette(): string
    {
        return $GLOBALS['TL_DCA'][self::TABLE]['palettes'][$this->step] ?? '';
    }

    #[\Override]
    public function getCurrentRecord(int|string|null $id = null, string|null $table = null): array|null
    {
        return $this->importValues;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        return $this->importValues;
    }

    public function isSubmitted(): bool
    {
        return self::TABLE === Input::post('FORM_SUBMIT');
    }

    public function hasErrors(): bool
    {
        return $this->noReload;
    }

    public function isValidSubmit(): bool
    {
        return $this->isSubmitted() && !$this->noReload;
    }

    /**
     * Adds a field for every column of a csv file, to assign the column to a field of the event, and composes the
     * palette "csv" with them.
     *
     * @param list<string>          $header         The names of the columns
     * @param array<string, string> $calendarFields The fields of the event with their labels
     */
    public function defineMappingFields(array $header, array $calendarFields): void
    {
        $fields = [];

        foreach ($header as $index => $name) {
            $field = 'mapping_'.$index;
            $fields[] = $field;

            $GLOBALS['TL_DCA'][self::TABLE]['fields'][$field] = [
                // The name comes from an uploaded file
                'label' => [StringUtil::specialchars($name), ''],
                'inputType' => 'select',
                'options' => $calendarFields,
                'eval' => ['includeBlankOption' => true, 'tl_class' => 'w50'],
            ];

            // Preselect the field with the name (or label) of the column
            foreach ($calendarFields as $key => $label) {
                if (0 === strcasecmp(trim($name), $key) || 0 === strcasecmp(trim($name), $label)) {
                    $this->importValues[$field] ??= $key;
                }
            }
        }

        $GLOBALS['TL_DCA'][self::TABLE]['palettes']['csv'] = '{csv_legend},encoding,dateFormat,timeFormat;{mapping_legend},'.implode(',', $fields);
    }

    /**
     * Splits the palette into boxes (legend, css class and fields).
     *
     * @return list<array{key: string, class: string, fields: list<string>}>
     */
    public function getBoxes(): array
    {
        $boxes = [];

        foreach (explode(';', $this->getPalette()) as $box) {
            $fields = array_values(array_filter(array_map(trim(...), explode(',', $box))));
            $key = '';
            $class = '';

            if ([] !== $fields && preg_match('/^\{(.+?)(?::(.+))?\}$/', $fields[0], $matches)) {
                [, $key, $class] = $matches + [2 => ''];
                array_shift($fields);
            }

            if ([] !== $fields) {
                $boxes[] = ['key' => $key, 'class' => 'hide' === $class ? 'collapsed' : $class, 'fields' => $fields];
            }
        }

        return $boxes;
    }

    /**
     * Renders (and validates, if the form was submitted) a field.
     */
    public function renderField(string $field): string
    {
        $this->strField = $field;
        $this->strInputName = $field;
        $this->varValue = $this->importValues[$field] ?? null;

        // The widgets of Contao validate the input and call save() for valid values
        return $this->row();
    }

    /**
     * The markup of the file upload (the class is configured per back end user).
     */
    public function renderUploader(): string
    {
        return $this->getUploader()->generateMarkup();
    }

    /**
     * @return list<string> The paths of the uploaded files
     */
    public function upload(string $folder): array
    {
        return $this->getUploader()->uploadTo($folder);
    }

    /**
     * The default period of an import: from the first day of the current year to the last day of the year after next.
     * The end is also used if the end date is left empty, so the number of imported recurrences is limited.
     */
    public function getDefaultDate(bool $start): string
    {
        $time = $start ? mktime(0, 0, 0, 1, 1, (int) date('Y')) : mktime(0, 0, 0, 12, 31, (int) date('Y') + 2);

        return date((string) Config::get('dateFormat'), (int) $time);
    }

    #[\Override]
    protected function save($varValue): void
    {
        $this->importValues[$this->strField] = $varValue;
    }

    private function getUploader(): FileUpload
    {
        if (null === $this->uploader) {
            $class = BackendUser::getInstance()->uploader;

            // See #4086
            if (!class_exists($class)) {
                $class = FileUpload::class;
            }

            $this->uploader = new $class();
        }

        return $this->uploader;
    }
}
