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
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Model\Registry;
use Contao\ModuleModel;
use Contao\PageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
     * @param array<string, string> $query
     */
    #[DataProvider('providePeriods')]
    public function testCollectsTheEventsOfThePeriodOfTheFormatAndTheUrl(string $format, array $query, bool $ignoreDynamic, string $expectedStart, string $expectedEnd): void
    {
        $period = [];
        $source = static function (EventlistModule $module, array $calendarIds, bool|null $featured, int $start, int $end) use (&$period): array {
            $period = [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)];

            return [];
        };

        $this->controller($source, $this->module(['cal_format' => $format, 'cal_ignoreDynamic' => $ignoreDynamic ? '1' : '']))(new Request($query), '5');

        $this->assertSame([$expectedStart, $expectedEnd], $period);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, bool, string, string}>
     */
    public static function providePeriods(): iterable
    {
        yield 'year of the url' => ['cal_year', ['year' => '2024'], false, '2024-01-01 00:00:00', '2024-12-31 23:59:59'];
        yield 'month of the url' => ['cal_month', ['month' => '202405'], false, '2024-05-01 00:00:00', '2024-05-31 23:59:59'];
        yield 'day of the url' => ['cal_day', ['day' => '20240501'], false, '2024-05-01 00:00:00', '2024-05-01 23:59:59'];
        yield 'url overrides the format' => ['cal_month', ['year' => '2024'], false, '2024-01-01 00:00:00', '2024-12-31 23:59:59'];
        yield 'current year' => ['cal_year', [], false, date('Y').'-01-01 00:00:00', date('Y').'-12-31 23:59:59'];
        yield 'current month' => ['cal_month', [], false, date('Y-m').'-01 00:00:00', date('Y-m-t').' 23:59:59'];
        yield 'current day' => ['cal_day', [], false, date('Y-m-d').' 00:00:00', date('Y-m-d').' 23:59:59'];
        yield 'ignored url' => ['cal_month', ['month' => '202405'], true, date('Y-m').'-01 00:00:00', date('Y-m-t').' 23:59:59'];
    }

    public function testIgnoresTheUrlForARelativeRange(): void
    {
        $period = [];
        $source = static function (EventlistModule $module, array $calendarIds, bool|null $featured, int $start, int $end) use (&$period): array {
            $period = [$start, $end];

            return [];
        };

        $this->controller($source, $this->module(['cal_format' => 'next_7']))(new Request(['month' => '202405']), '5');

        $this->assertEqualsWithDelta(time(), $period[0], 5);
        $this->assertEqualsWithDelta(7 * 86400, $period[1] - $period[0], 3600, 'a week, give or take a change of the daylight saving time');
    }

    public function testAnInvalidDateInTheUrlIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->controller(static fn (): array => [], $this->module(['cal_format' => 'cal_month']))(new Request(['month' => '202413']), '5');
    }

    #[DataProvider('provideFeatured')]
    public function testPassesTheFeaturedSettingOnToTheEvents(string $setting, bool|null $expected): void
    {
        $featured = 'not called';
        $source = static function (EventlistModule $module, array $calendarIds, bool|null $value) use (&$featured): array {
            $featured = $value;

            return [];
        };

        $this->controller($source, $this->module(['cal_featured' => $setting]))(new Request(), '5');

        $this->assertSame($expected, $featured);
    }

    /**
     * @return iterable<string, array{string, bool|null}>
     */
    public static function provideFeatured(): iterable
    {
        yield 'featured' => ['featured', true];
        yield 'unfeatured' => ['unfeatured', false];
        yield 'all' => ['all_items', null];
    }

    public function testNamesTheFileAfterTheEventlist(): void
    {
        $event = $this->event();

        $response = $this->controller(static fn (): array => [20240501 => [0 => [$event]]], $this->module(['name' => 'Vereinstermine / Jugend']))(new Request(), '5');

        $this->assertStringContainsString('.ics', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Vereinstermine', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('/', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('text/calendar', $response->headers->get('Content-Type'));
    }

    public function testNamesTheFileAfterTheIdIfTheEventlistHasNoName(): void
    {
        $event = $this->event();

        $response = $this->controller(static fn (): array => [20240501 => [0 => [$event]]], $this->module())(new Request(), '5');

        $this->assertStringContainsString('5.ics', (string) $response->headers->get('Content-Disposition'));
    }

    public function testDoesNotCacheTheResponseOfProtectedElements(): void
    {
        $event = $this->event();
        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturn(true)
        ;

        $response = $this->controller(static fn (): array => [20240501 => [0 => [$event]]], $this->module(['protected' => '1', 'groups' => serialize([2])]), null, $security)(new Request(), '5');

        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testDoesNotCacheTheResponseWithEventsOfAProtectedCalendar(): void
    {
        $event = $this->event();
        $calendar = new CalendarModel();
        $calendar->id = 1;
        $calendar->protected = true;
        $calendar->groups = serialize([2]);

        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturn(true)
        ;

        $response = $this->controller(static fn (): array => [20240501 => [0 => [$event]]], $this->module(), [$calendar], $security)(new Request(), '5');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testExportsEachEventOnceAlthoughItOccursOnSeveralDays(): void
    {
        $event = $this->event();

        $source = static fn (): array => [20240501 => [0 => [$event]], 20240502 => [0 => [$event]], 20240503 => [0 => [$event], 3600 => [$event]]];

        $response = $this->controller($source, $this->module())(new Request(), '5');

        $this->assertSame(1, substr_count((string) $response->getContent(), 'BEGIN:VEVENT'));
    }

    public function testRejectsANonNumericId(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->controller(static fn (): array => [], $this->module())(new Request(), 'abc');
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
    private function controller(callable $eventSource, ModuleModel|null $eventlist, array|null $calendars = null, Security|null $security = null): EventlistIcsExportController
    {
        $calendar = new CalendarModel();
        $calendar->id = 1;
        $calendar->title = 'Club';

        $security ??= $this->createStub(Security::class);
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
                return ($this->eventSource)($module, $calendarIds, $featured, $start, $end);
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
