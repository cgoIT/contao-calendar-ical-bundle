<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Security;

use Cgoit\ContaoCalendarIcalBundle\Security\ExportAccessChecker;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\ModuleModel;
use Symfony\Bundle\SecurityBundle\Security;

final class ExportAccessCheckerTest extends ContaoTestCase
{
    public function testPublicCalendarIsExportable(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->canExportCalendar($this->calendar(1, false)));
    }

    public function testProtectedCalendarRequiresGroupMembership(): void
    {
        $calendar = $this->calendar(1, true, serialize([3]));

        $this->assertFalse($this->checker(memberInGroups: false)->canExportCalendar($calendar));
        $this->assertTrue($this->checker(memberInGroups: true)->canExportCalendar($calendar));
    }

    public function testFilterCalendarsDropsInaccessibleOnes(): void
    {
        $public = $this->calendar(1, false);
        $protected = $this->calendar(2, true);

        $this->assertSame([$public], $this->checker(memberInGroups: false)->filterCalendars([$public, $protected]));
        $this->assertSame([], $this->checker()->filterCalendars(null));
    }

    public function testHasProtectedCalendar(): void
    {
        $checker = $this->checker();

        $this->assertTrue($checker->hasProtectedCalendar([$this->calendar(1, false), $this->calendar(2, true)]));
        $this->assertFalse($checker->hasProtectedCalendar([$this->calendar(1, false)]));
        $this->assertFalse($checker->hasProtectedCalendar(null));
    }

    public function testUnpublishedContentElementIsNotVisible(): void
    {
        $this->bootContao();
        $checker = $this->checker();

        $this->assertFalse($checker->isElementVisible($this->content(['invisible' => true])));
        $this->assertFalse($checker->isElementVisible($this->content(['start' => time() + 3600])));
        $this->assertFalse($checker->isElementVisible($this->content(['stop' => time() - 3600])));
        $this->assertTrue($checker->isElementVisible($this->content([])));
    }

    public function testGuestsOnlyElementIsHiddenFromMembers(): void
    {
        $this->bootContao();
        $module = $this->module(['guests' => true]);

        $this->assertTrue($this->checker(isMember: false)->isElementVisible($module));
        $this->assertFalse($this->checker(isMember: true)->isElementVisible($module));
    }

    public function testProtectedModuleRequiresGroupMembership(): void
    {
        $this->bootContao();
        $module = $this->module(['protected' => true, 'groups' => serialize(['2'])]);

        $this->assertFalse($this->checker(memberInGroups: false)->isElementVisible($module));
        $this->assertTrue($this->checker(memberInGroups: true)->isElementVisible($module));
    }

    private function checker(bool $memberInGroups = false, bool $isMember = false): ExportAccessChecker
    {
        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturnCallback(static fn (string $attribute): bool => match ($attribute) {
                ContaoCorePermissions::MEMBER_IN_GROUPS => $memberInGroups,
                'ROLE_MEMBER' => $isMember,
                default => false,
            })
        ;

        return new ExportAccessChecker($security);
    }

    private function calendar(int $id, bool $protected, string|null $groups = null): CalendarModel
    {
        $this->bootContao();

        $calendar = new CalendarModel();
        $calendar->id = $id;
        $calendar->protected = $protected;
        $calendar->groups = $groups;

        return $calendar;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function content(array $data): ContentModel
    {
        $model = new ContentModel();

        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function module(array $data): ModuleModel
    {
        $model = new ModuleModel();

        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }

        return $model;
    }
}
