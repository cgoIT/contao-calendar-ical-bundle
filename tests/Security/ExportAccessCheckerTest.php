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
use Contao\ArticleModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Model\Registry;
use Contao\ModuleModel;
use Contao\PageModel;
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

    public function testElementInUnpublishedOrProtectedArticleIsNotVisible(): void
    {
        $this->bootContao();
        $page = $this->page(1, ['published' => true]);

        $cases = [
            'published' => [['published' => true], true],
            'unpublished' => [['published' => false], false],
            'not yet published' => [['published' => true, 'start' => time() + 3600], false],
            'expired' => [['published' => true, 'stop' => time() - 3600], false],
            'protected' => [['published' => true, 'protected' => true, 'groups' => serialize(['3'])], false],
        ];

        $id = 10;

        foreach ($cases as $name => [$data, $expected]) {
            $this->article(++$id, ['pid' => 1] + $data);

            $this->assertSame($expected, $this->checker(pages: [1 => $page])->isElementVisible($this->content(['ptable' => 'tl_article', 'pid' => $id])), $name);
        }
    }

    public function testElementInArticleWithoutTableValueUsesTheArticle(): void
    {
        $this->bootContao();
        $this->article(10, ['pid' => 1, 'published' => false]);

        $this->assertFalse($this->checker(pages: [1 => $this->page(1, ['published' => true])])->isElementVisible($this->content(['ptable' => '', 'pid' => 10])));
    }

    public function testElementOnUnpublishedOrProtectedPageIsNotVisible(): void
    {
        $this->bootContao();
        $this->article(10, ['pid' => 1, 'published' => true]);

        $cases = [
            'published' => [['published' => true], true],
            'unpublished' => [['published' => false], false],
            'expired' => [['published' => true, 'stop' => time() - 3600], false],
            'protected (inherited)' => [['published' => true, 'protected' => true, 'groups' => serialize(['3'])], false],
        ];

        foreach ($cases as $name => [$data, $expected]) {
            $checker = $this->checker(pages: [1 => $this->page(1, $data)]);

            $this->assertSame($expected, $checker->isElementVisible($this->content(['ptable' => 'tl_article', 'pid' => 10])), $name);
        }

        $this->assertFalse($this->checker()->isElementVisible($this->content(['ptable' => 'tl_article', 'pid' => 10])), 'missing page');
    }

    public function testMemberOfTheGroupsSeesProtectedArticleAndPage(): void
    {
        $this->bootContao();
        $this->article(10, ['pid' => 1, 'published' => true, 'protected' => true, 'groups' => serialize(['3'])]);
        $page = $this->page(1, ['published' => true, 'protected' => true, 'groups' => serialize(['3'])]);

        $this->assertTrue($this->checker(memberInGroups: true, pages: [1 => $page])->isElementVisible($this->content(['ptable' => 'tl_article', 'pid' => 10])));
    }

    public function testNestedElementDependsOnItsParentElement(): void
    {
        $this->bootContao();
        $this->article(10, ['pid' => 1, 'published' => true]);
        $checker = $this->checker(pages: [1 => $this->page(1, ['published' => true])]);

        $this->contentRecord(20, ['ptable' => 'tl_article', 'pid' => 10, 'invisible' => true]);
        $this->contentRecord(21, ['ptable' => 'tl_article', 'pid' => 10]);

        $this->assertFalse($checker->isElementVisible($this->content(['ptable' => 'tl_content', 'pid' => 20])));
        $this->assertTrue($checker->isElementVisible($this->content(['ptable' => 'tl_content', 'pid' => 21])));
    }

    public function testCyclicNestingIsNotVisible(): void
    {
        $this->bootContao();
        $this->contentRecord(30, ['ptable' => 'tl_content', 'pid' => 31]);
        $this->contentRecord(31, ['ptable' => 'tl_content', 'pid' => 30]);

        $this->assertFalse($this->checker()->isElementVisible($this->content(['ptable' => 'tl_content', 'pid' => 30])));
    }

    /**
     * @param array<int, PageModel> $pages
     */
    private function checker(bool $memberInGroups = false, bool $isMember = false, array $pages = []): ExportAccessChecker
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

        return new class($security, $pages) extends ExportAccessChecker {
            /**
             * @param array<int, PageModel> $pages
             */
            public function __construct(Security $security, private readonly array $pages)
            {
                parent::__construct($security);
            }

            protected function loadPage(int $id): PageModel|null
            {
                return $this->pages[$id] ?? null;
            }
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(int $id, array $data): PageModel
    {
        $page = new PageModel();
        $page->id = $id;

        foreach ($data as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function article(int $id, array $data): void
    {
        $article = new ArticleModel();
        $article->id = $id;

        foreach ($data as $key => $value) {
            $article->{$key} = $value;
        }

        Registry::getInstance()->register($article);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function contentRecord(int $id, array $data): void
    {
        $content = $this->content(['id' => $id] + $data);
        Registry::getInstance()->register($content);
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
        // by default the element belongs to another table (e.g. news), so only the element itself is checked
        $data += ['ptable' => 'tl_news', 'pid' => 1];

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
