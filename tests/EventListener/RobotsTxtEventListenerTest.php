<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\EventListener;

use Cgoit\ContaoCalendarIcalBundle\EventListener\RobotsTxtEventListener;
use Contao\CoreBundle\Event\RobotsTxtEvent;
use Contao\PageModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use webignition\RobotsTxt\File\File;
use webignition\RobotsTxt\File\Parser;

final class RobotsTxtEventListenerTest extends TestCase
{
    public function testAddsUserAgentAndDisallowForEmptyFile(): void
    {
        $file = new File();
        $this->dispatch($file);

        $this->assertSame("user-agent:*\ndisallow:/_event/", trim((string) $file));
    }

    public function testAddsDisallowToEveryExistingRecord(): void
    {
        $file = $this->parse("User-agent: Googlebot\nDisallow: /private/\n\nUser-agent: *\nAllow: /\n");
        $this->dispatch($file);

        $content = (string) $file;
        $this->assertSame(2, substr_count($content, 'disallow:/_event/'));
        $this->assertStringContainsString('disallow:/private/', $content);
    }

    private function parse(string $source): File
    {
        $parser = new Parser();
        $parser->setSource($source);

        return $parser->getFile();
    }

    private function dispatch(File $file): void
    {
        (new RobotsTxtEventListener())(new RobotsTxtEvent($file, new Request(), $this->createStub(PageModel::class)));
    }
}
