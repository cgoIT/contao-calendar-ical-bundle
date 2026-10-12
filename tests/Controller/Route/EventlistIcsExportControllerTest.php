<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Controller\Route;

use Cgoit\ContaoCalendarIcalBundle\Controller\Route\EventlistIcsExportController;
use Cgoit\ContaoCalendarIcalBundle\Export\EventlistModule;
use Cgoit\ContaoCalendarIcalBundle\Export\IcsExport;
use Cgoit\ContaoCalendarIcalBundle\Security\ExportAccessChecker;
use Cgoit\ContaoCalendarIcalBundle\Tests\ContaoTestCase;
use Cgoit\ContaoCalendarIcalBundle\Util\ResponseUtil;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Registry;
use Contao\ModuleModel;
use Contao\PageModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EventlistIcsExportControllerTest extends ContaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootContao();
        $GLOBALS['TL_LANG']['MSC'] = ['cal_empty' => '', 'cal_emptyDay' => '', 'cal_emptyMonth' => '', 'cal_emptyYear' => ''];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['objPage'], $GLOBALS['TL_LANG']);

        parent::tearDown();
    }

    public function testExportsTheEventsOfTheEventlist(): void
    {
        $event = $this->event();
        $calls = [];

        $source = static function (EventlistModule $module, array $calendarIds, bool|null $featured) use ($event, &$calls): array {
            $calls[] = [$module, $calendarIds, $featured];

            return [20240501 => [0 => [$event]]];
        };

        $response = $this->controller($source, $this->module(['cal_noSpan' => '1', 'cal_featured' => 'featured']))(new Request(), '5');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('SUMMARY:Training', (string) $response->getContent());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('no-store'));

        $this->assertCount(1, $calls);
        $this->assertSame([1], $calls[0][1]);
        $this->assertTrue($calls[0][2]);
        $this->assertSame('1', $calls[0][0]->cal_noSpan, 'The module hands the settings of the list to the hooks');
    }

    public function testRestoresThePageContextAfterwards(): void
    {
        $request = new Request();

        $this->controller(static fn (): array => [], $this->module())($request, '5');

        $this->assertArrayNotHasKey('objPage', $GLOBALS);
        $this->assertFalse($request->attributes->has('pageModel'));
    }

    public function testRestoresThePageContextIfCollectingTheEventsFails(): void
    {
        $page = new PageModel();
        $GLOBALS['objPage'] = $page;
        $request = new Request();

        $source = static function (): array {
            throw new \RuntimeException('boom');
        };

        try {
            $this->controller($source, $this->module())($request, '5');
            $this->fail('The exception was expected');
        } catch (\RuntimeException) {
        }

        $this->assertSame($page, $GLOBALS['objPage']);
        $this->assertFalse($request->attributes->has('pageModel'));
    }

    public function testUnknownEventlistIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(static fn (): array => [], null)(new Request(), '5');
    }

    public function testRespondsWithNoContentIfNoCalendarIsAccessible(): void
    {
        $called = false;
        $source = static function () use (&$called): array {
            $called = true;

            return [];
        };

        $protected = new CalendarModel();
        $protected->id = 1;
        $protected->protected = true;

        $response = $this->controller($source, $this->module(), [$protected])(new Request(), '5');

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($called);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function module(array $data = []): ModuleModel
    {
        $module = new ModuleModel();
        $module->id = 5;
        $module->type = 'eventlist';
        $module->cal_calendar = serialize([1]);
        $module->cal_format = 'next_365';

        foreach ($data as $key => $value) {
            $module->{$key} = $value;
        }

        return $module;
    }

    private function event(): CalendarEventsModel
    {
        $event = new CalendarEventsModel();
        $event->id = 100;
        $event->pid = 1;
        $event->title = 'Training';
        $event->addTime = false;
        $event->startDate = (new \DateTimeImmutable('2024-05-01 00:00', new \DateTimeZone('Europe/Berlin')))->getTimestamp();
        $event->endDate = null;

        $calendar = new CalendarModel();
        $calendar->id = 1;
        $calendar->title = 'Club';

        Registry::getInstance()->register($calendar);
        Registry::getInstance()->register($event);

        return $event;
    }

    /**
     * @param array<CalendarModel>|null $calendars
     */
    private function controller(callable $eventSource, ModuleModel|null $eventlist, array|null $calendars = null): EventlistIcsExportController
    {
        $calendar = new CalendarModel();
        $calendar->id = 1;
        $calendar->title = 'Club';

        $security = $this->createStub(Security::class);
        $parser = $this->createStub(InsertTagParser::class);
        $parser
            ->method('replaceInline')
            ->willReturnArgument(0)
        ;

        return new class($eventSource(...), $eventlist, $calendars ?? [$calendar], new ResponseUtil(), new IcsExport($parser, new EventDispatcher()), $this->createStub(ContaoFramework::class), new ExportAccessChecker($security)) extends EventlistIcsExportController {
            /**
             * @param array<CalendarModel> $calendars
             */
            public function __construct(
                private readonly \Closure $eventSource,
                private readonly ModuleModel|null $eventlist,
                private readonly array $calendars,
                ResponseUtil $responseUtil,
                IcsExport $icsExport,
                ContaoFramework $framework,
                ExportAccessChecker $accessChecker,
            ) {
                parent::__construct($responseUtil, $icsExport, $framework, $accessChecker);
            }

            protected function findEvents(EventlistModule $module, array $calendarIds, int $start, int $end, bool|null $featured): array
            {
                return ($this->eventSource)($module, $calendarIds, $featured);
            }

            protected function findFallbackPage(Request $request): PageModel
            {
                return new PageModel();
            }

            protected function loadLanguageFiles(): void
            {
            }

            protected function findEventlist(int $id): ModuleModel|null
            {
                return $this->eventlist;
            }

            protected function findCalendars(ContentModel|ModuleModel $objEventlist): array
            {
                return $this->calendars;
            }
        };
    }
}
