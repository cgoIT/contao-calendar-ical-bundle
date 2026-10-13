<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\EventListener\DataContainer;

use Cgoit\ContaoCalendarIcalBundle\Controller\Backend\ImportBackendController;
use Cgoit\ContaoCalendarIcalBundle\EventListener\DataContainer\ImportButtonCallback;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarBundle\Security\ContaoCalendarPermissions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ImportButtonCallbackTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao(['contao.sanitizer.allowed_url_protocols' => ['http', 'https']]);
    }

    public function testCreatesTheLinkToTheImportOfTheCalendar(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with(
                ImportBackendController::ROUTE_NAME,
                ['id' => 3],
            )
            ->willReturn('/contao/calendar-ical/import/3?a=1&b=2')
        ;

        $html = $this->createCallback($this->security(true), $this->requestStack('3'), $router)(null, 'Import', 'Import "events"', 'header_import', ' data-x="1"');

        $this->assertSame('<a href="/contao/calendar-ical/import/3?a=1&amp;b=2" class="header_import" title="Import &quot;events&quot;" data-x="1">Import</a> ', $html);
    }

    public function testChecksThePermissionToEditTheCalendar(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->once())
            ->method('isGranted')
            ->with(
                ContaoCalendarPermissions::USER_CAN_EDIT_CALENDAR,
                3,
            )
            ->willReturn(true)
        ;

        $this->createCallback($security, $this->requestStack('3'), $this->createStub(UrlGeneratorInterface::class))(null, 'Import', 'Import', 'header_import', '');
    }

    public function testHidesTheButtonWithoutPermission(): void
    {
        $this->assertSame('', $this->createCallback($this->security(false), $this->requestStack('3'), $this->createStub(UrlGeneratorInterface::class))(null, 'Import', 'Import', 'header_import', ''));
    }

    public function testHidesTheButtonWithoutCalendar(): void
    {
        foreach ([null, '0', 'abc'] as $id) {
            $this->assertSame('', $this->createCallback($this->security(true), $this->requestStack($id), $this->createStub(UrlGeneratorInterface::class))(null, 'Import', 'Import', 'header_import', ''), (string) $id);
        }
    }

    private function createCallback(Security $security, RequestStack $requestStack, UrlGeneratorInterface $router): ImportButtonCallback
    {
        return new ImportButtonCallback($security, $requestStack, $router);
    }

    private function security(bool $granted): Security
    {
        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturn($granted)
        ;

        return $security;
    }

    private function requestStack(string|null $id): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request(null === $id ? [] : ['id' => $id]));

        return $stack;
    }
}
