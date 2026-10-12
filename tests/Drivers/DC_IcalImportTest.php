<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Drivers;

use Cgoit\ContaoCalendarIcalBundle\Drivers\DC_IcalImport;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;

final class DC_IcalImportTest extends ContaoTestCase
{
    private DC_IcalImport $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();
        $this->driver = (new \ReflectionClass(DC_IcalImport::class))->newInstanceWithoutConstructor();

        $GLOBALS['TL_DCA'][DC_IcalImport::TABLE] = [
            'palettes' => [
                'upload' => '{source_legend},icssource;{range_legend:hide},startDate,endDate;{options_legend},deleteCalendar',
                'plain' => 'title,description',
            ],
            'fields' => [],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_DCA'][DC_IcalImport::TABLE]);

        parent::tearDown();
    }

    public function testSplitsThePaletteIntoBoxes(): void
    {
        $this->setStep('upload');

        $this->assertSame(
            [
                ['key' => 'source_legend', 'class' => '', 'fields' => ['icssource']],
                ['key' => 'range_legend', 'class' => 'collapsed', 'fields' => ['startDate', 'endDate']],
                ['key' => 'options_legend', 'class' => '', 'fields' => ['deleteCalendar']],
            ],
            $this->driver->getBoxes(),
        );
    }

    public function testAPaletteWithoutLegendIsOneBox(): void
    {
        $this->setStep('plain');

        $this->assertSame([['key' => '', 'class' => '', 'fields' => ['title', 'description']]], $this->driver->getBoxes());
    }

    public function testAnUnknownPaletteHasNoBoxes(): void
    {
        $this->setStep('unknown');

        $this->assertSame([], $this->driver->getBoxes());
    }

    public function testDefinesAFieldForEveryColumnOfACsvFile(): void
    {
        $this->setStep('csv');
        $this->setValues([]);

        $this->driver->defineMappingFields(['Titel', 'Ort', 'Notiz'], ['title' => 'Titel', 'location' => 'Ort']);

        $this->assertSame('{csv_legend},encoding,dateFormat,timeFormat;{mapping_legend},mapping_0,mapping_1,mapping_2', $GLOBALS['TL_DCA'][DC_IcalImport::TABLE]['palettes']['csv']);
        $this->assertSame('select', $GLOBALS['TL_DCA'][DC_IcalImport::TABLE]['fields']['mapping_2']['inputType']);
        $this->assertSame(['title' => 'Titel', 'location' => 'Ort'], $GLOBALS['TL_DCA'][DC_IcalImport::TABLE]['fields']['mapping_0']['options']);
        $this->assertTrue($GLOBALS['TL_DCA'][DC_IcalImport::TABLE]['fields']['mapping_0']['eval']['includeBlankOption']);
    }

    public function testPreselectsTheFieldWithTheNameOfTheColumn(): void
    {
        $this->setStep('csv');
        $this->setValues([]);

        // The name matches the label or the name of the field, without regard to case
        $this->driver->defineMappingFields(['titel', 'LOCATION', 'Notiz'], ['title' => 'Titel', 'location' => 'Ort']);

        $this->assertSame(['mapping_0' => 'title', 'mapping_1' => 'location'], $this->driver->getValues());
    }

    public function testDoesNotOverwriteAValueThatWasSetAlready(): void
    {
        $this->setStep('csv');
        $this->setValues(['mapping_0' => 'location']);

        $this->driver->defineMappingFields(['Titel'], ['title' => 'Titel', 'location' => 'Ort']);

        $this->assertSame('location', $this->driver->getValues()['mapping_0']);
    }

    public function testEscapesTheNamesOfTheColumns(): void
    {
        $this->setStep('csv');
        $this->setValues([]);

        // The names come from an uploaded file
        $this->driver->defineMappingFields(['<script>alert(1)</script>"'], ['title' => 'Titel']);

        $label = $GLOBALS['TL_DCA'][DC_IcalImport::TABLE]['fields']['mapping_0']['label'][0];

        $this->assertStringNotContainsString('<script>', (string) $label);
        $this->assertStringContainsString('&lt;script&gt;', (string) $label);
    }

    public function testCalculatesTheDefaultDatesOfTheCurrentYear(): void
    {
        $this->assertSame(date('Y').'-01-01', $this->driver->getDefaultDate(true));
        $this->assertSame(date('Y').'-12-31', $this->driver->getDefaultDate(false));
    }

    private function setStep(string $step): void
    {
        (new \ReflectionProperty(DC_IcalImport::class, 'step'))->setValue($this->driver, $step);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function setValues(array $values): void
    {
        (new \ReflectionProperty(DC_IcalImport::class, 'importValues'))->setValue($this->driver, $values);
    }
}
