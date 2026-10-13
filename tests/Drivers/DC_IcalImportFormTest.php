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
use Cgoit\ContaoCalendarIcalBundle\Tests\DatabaseTestCase;
use Contao\BackendUser;
use Contao\CheckBox;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Session\Attribute\ArrayAttributeBag;
use Contao\FileUpload;
use Contao\SelectMenu;
use Contao\System;
use Contao\TemplateLoader;
use Contao\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Renders and validates the fields of the import form with the real widgets of Contao.
 */
final class DC_IcalImportFormTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The data container is loaded from the cache of the DCA; the cache of the test holds the one of the bundle
        $cacheDir = $this->getTempDir().'/cache';
        (new Filesystem())->copy(__DIR__.'/../../contao/dca/tl_calendar_ical_import.php', $cacheDir.'/contao/dca/tl_calendar_ical_import.php');

        // The back end is requested; the form fields are registered by the configuration of the core
        $scopeMatcher = $this->createStub(ScopeMatcher::class);
        $scopeMatcher
            ->method('isBackendRequest')
            ->willReturn(true)
        ;
        System::getContainer()->set('contao.routing.scope_matcher', $scopeMatcher);
        $GLOBALS['BE_FFL'] = ['text' => TextField::class, 'checkbox' => CheckBox::class, 'select' => SelectMenu::class];

        // The texts of the widgets
        $GLOBALS['TL_LANG']['MSC'] = ['mandatory' => 'Mandatory field', 'dayShortLength' => 2, 'datepicker' => 'Date picker', 'weekOffset' => 1, 'titleFormat' => '%B %Y'];
        $GLOBALS['TL_LANG']['DATE'] = [];
        $GLOBALS['TL_LANG']['ERR'] = ['mandatory' => 'Please fill in the field "%s"!', 'digit' => 'Field "%s" must contain digits only!', 'date' => 'Please enter a date matching the format "%s"!', 'invalid' => 'Please choose a valid option!', 'invalidDate' => '"%s" is not a valid date!'];

        // The templates of the widgets are those of the core; the icons (e.g. the one of an error) are not needed
        $container = System::getContainer();
        $container->setParameter('kernel.project_dir', \dirname(__DIR__, 2));
        $container->setParameter('contao.backend.icons', []);
        $container->setParameter('contao.web_dir', \dirname(__DIR__, 2).'/public');

        $insertTags = $this->createStub(InsertTagParser::class);
        $insertTags
            ->method('replaceInline')
            ->willReturnArgument(0)
        ;
        $container->set('contao.insert_tag.parser', $insertTags);
        TemplateLoader::addFiles(['be_widget' => 'vendor/contao/core-bundle/contao/templates/backend', 'be_widget_chk' => 'vendor/contao/core-bundle/contao/templates/backend']);

        // By default the form is requested, not submitted
        $this->push(Request::create('/contao/calendar-ical/import/1'));

        // The editor is no administrator and has no permission for the fields of the virtual table
        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturn(false)
        ;
        System::getContainer()->set('security.helper', $security);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_FFL'], $GLOBALS['TL_LANG']);
        TemplateLoader::reset();

        parent::tearDown();
    }

    public function testInitializes(): void
    {
        $driver = new DC_IcalImport($this->createFramework());
        $driver->initialize(1, 'upload');

        $this->assertSame([], $driver->getValues());
        $this->assertNotSame([], $driver->getBoxes());
    }

    public function testRendersAFieldWithoutPermissionForTheFieldsOfTheVirtualTable(): void
    {
        $driver = $this->driver('upload');

        // Not an administrator: the field must not be treated as excluded, otherwise Contao denies the access
        $this->assertStringContainsString('name="timeshift"', $driver->renderField('timeshift'));
    }

    public function testDeniesAFieldThatIsExcluded(): void
    {
        $driver = $this->driver('upload');
        $GLOBALS['TL_DCA'][DC_IcalImport::TABLE]['fields']['timeshift']['exclude'] = true;

        $this->expectException(AccessDeniedException::class);

        $driver->renderField('timeshift');
    }

    public function testCollectsTheValidValuesOfASubmittedForm(): void
    {
        $this->submit(['timeshift' => '3', 'filterEventTitle' => 'Training', 'deleteCalendar' => '1']);
        $driver = $this->driver('upload');

        foreach (['timeshift', 'filterEventTitle', 'deleteCalendar'] as $field) {
            $driver->renderField($field);
        }

        $this->assertTrue($driver->isSubmitted());
        $this->assertFalse($driver->hasErrors());
        $this->assertTrue($driver->isValidSubmit());
        $this->assertSame(['timeshift' => '3', 'filterEventTitle' => 'Training', 'deleteCalendar' => '1'], $driver->getValues());
    }

    public function testRejectsInvalidInput(): void
    {
        $this->submit(['timeshift' => 'abc', 'startDate' => '']);
        $driver = $this->driver('upload');

        $html = $driver->renderField('timeshift').$driver->renderField('startDate');

        $this->assertTrue($driver->isSubmitted());
        $this->assertTrue($driver->hasErrors());
        $this->assertFalse($driver->isValidSubmit());
        $this->assertSame([], $driver->getValues(), 'invalid values are not collected');
        $this->assertStringContainsString('tl_error', $html);
    }

    public function testValidatesTheDates(): void
    {
        $this->submit(['startDate' => '2024-05-01', 'endDate' => '2024-02-31']);
        $driver = $this->driver('upload');

        $driver->renderField('startDate');
        $driver->renderField('endDate');

        $this->assertSame(['startDate' => '2024-05-01'], $driver->getValues());
        $this->assertTrue($driver->hasErrors());
    }

    public function testDoesNotValidateIfTheFormWasNotSubmitted(): void
    {
        $driver = $this->driver('upload', ['timeshift' => '2']);

        $html = $driver->renderField('timeshift');

        $this->assertFalse($driver->isSubmitted());
        $this->assertFalse($driver->isValidSubmit());
        $this->assertFalse($driver->hasErrors());
        $this->assertStringContainsString('value="2"', $html, 'the value that was set is shown');
    }

    public function testAnotherFormIsNotTakenAsSubmitted(): void
    {
        $this->push(Request::create('/', 'POST', ['FORM_SUBMIT' => 'tl_other', 'timeshift' => '3']));

        $this->assertFalse($this->driver('upload')->isSubmitted());
    }

    public function testRendersAndValidatesTheMappingOfACsvFile(): void
    {
        $this->submit(['mapping_0' => 'title', 'mapping_1' => 'nonsense']);
        $driver = $this->driver('csv');

        $driver->defineMappingFields(['Titel', 'Datum'], ['title' => 'Titel', 'startDate' => 'Beginn']);
        $this->assertStringContainsString('<option value="title"', $driver->renderField('mapping_0'));
        $driver->renderField('mapping_1');

        $this->assertSame(['mapping_0' => 'title'], $driver->getValues());
        $this->assertTrue($driver->hasErrors(), 'a value that is not an option is invalid');
    }

    public function testTheFieldsOfTheMappingAreNotExcluded(): void
    {
        $driver = $this->driver('csv');
        $driver->defineMappingFields(['Titel'], ['title' => 'Titel']);

        $this->assertStringContainsString('name="mapping_0"', $driver->renderField('mapping_0'));
    }

    public function testReturnsThePaletteAndTheValuesAsTheCurrentRecord(): void
    {
        $driver = $this->driver('timezone_missing', ['timezone' => 'Europe/Berlin']);

        $this->assertSame('{timezone_legend},timezone', $driver->getPalette());
        $this->assertSame(['timezone' => 'Europe/Berlin'], $driver->getCurrentRecord());
    }

    public function testAPaletteThatDoesNotExistIsEmpty(): void
    {
        $this->assertSame('', $this->driver('unknown')->getPalette());
    }

    public function testRendersTheUploaderOfTheCoreIfTheClassOfTheUserDoesNotExist(): void
    {
        $this->setUploader('Uploader\\DoesNotExist');

        $this->assertStringContainsString('type="file"', $this->driver('upload')->renderUploader());
    }

    public function testRefusesAnInsecureTargetFolder(): void
    {
        $this->setUploader(FileUpload::class);

        $this->expectException(\InvalidArgumentException::class);

        $this->driver('upload')->upload('files/../secret');
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function driver(string $palette, array $defaults = []): DC_IcalImport
    {
        $driver = new DC_IcalImport($this->createFramework());
        $driver->initialize(1, $palette, $defaults);

        return $driver;
    }

    /**
     * @param array<string, string> $post
     */
    private function submit(array $post): void
    {
        $this->push(Request::create('/contao/calendar-ical/import/1', 'POST', ['FORM_SUBMIT' => 'tl_calendar_ical_import'] + $post));
    }

    private function setUploader(string $class): void
    {
        $user = BackendUser::getInstance();
        (new \ReflectionProperty(BackendUser::class, 'arrData'))->setValue($user, ['id' => 7, 'uploader' => $class]);
    }

    private function push(Request $request): void
    {
        $session = new Session(new MockArraySessionStorage());
        $bag = new ArrayAttributeBag('_contao_be_attributes');
        $bag->setName('contao_backend');
        $session->registerBag($bag);
        $request->setSession($session);
        $this->requestStack()->push($request);
    }

    private function requestStack(): RequestStack
    {
        return System::getContainer()->get('request_stack');
    }
}
