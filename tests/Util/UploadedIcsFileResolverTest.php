<?php

declare(strict_types=1);

/*
 * This file is part of cgoit\contao-calendar-ical-bundle for Contao Open Source CMS.
 *
 * @copyright  Copyright (c), cgoIT
 * @author     cgoIT <https://cgo-it.de>
 * @license    LGPL-3.0-or-later
 */

namespace Cgoit\ContaoCalendarIcalBundle\Tests\Util;

use Cgoit\ContaoCalendarIcalBundle\Util\UploadedIcsFileResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class UploadedIcsFileResolverTest extends TestCase
{
    private string $projectDir;

    private UploadedIcsFileResolver $resolver;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/cgoit-ical-resolver-'.bin2hex(random_bytes(6));

        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->projectDir.'/system/tmp/upload.ics', 'BEGIN:VCALENDAR');
        $filesystem->dumpFile($this->projectDir.'/system/tmp/upload.csv', 'a;b');
        $filesystem->dumpFile($this->projectDir.'/system/config/localconfig.ics', 'secret');
        $filesystem->dumpFile($this->projectDir.'/.env.ics', 'secret');
        $filesystem->dumpFile(\dirname($this->projectDir).'/'.basename($this->projectDir).'-outside.ics', 'secret');

        $this->resolver = new UploadedIcsFileResolver($this->projectDir);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->projectDir);
        $filesystem->remove(\dirname($this->projectDir).'/'.basename($this->projectDir).'-outside.ics');
    }

    public function testAcceptsUploadedIcsFile(): void
    {
        $this->assertSame('system/tmp/upload.ics', $this->resolver->resolve('system/tmp/upload.ics'));
        $this->assertSame('system/tmp/upload.ics', $this->resolver->resolve('/system/tmp/upload.ics'));
    }

    public function testRejectsOtherExtensions(): void
    {
        $this->assertNull($this->resolver->resolve('system/tmp/upload.csv'));
    }

    public function testRejectsFilesOutsideOfUploadDirectory(): void
    {
        $this->assertNull($this->resolver->resolve('system/config/localconfig.ics'));
        $this->assertNull($this->resolver->resolve('.env.ics'));
        $this->assertNull($this->resolver->resolve('system/tmp/../config/localconfig.ics'));
        $this->assertNull($this->resolver->resolve('../'.basename($this->projectDir).'-outside.ics'));
        $this->assertNull($this->resolver->resolve($this->projectDir.'/../'.basename($this->projectDir).'-outside.ics'));
        $this->assertNull($this->resolver->resolve('/etc/passwd'));
    }

    public function testAllowsTheGivenExtensionsOnly(): void
    {
        (new Filesystem())->dumpFile($this->projectDir.'/system/tmp/events.csv', 'a');

        $this->assertNull($this->resolver->resolve('system/tmp/events.csv'), 'Only ics files are allowed by default');
        $this->assertSame('system/tmp/events.csv', $this->resolver->resolve('system/tmp/events.csv', ['csv']));
    }

    public function testRejectsMissingFilesAndDirectories(): void
    {
        $this->assertNull($this->resolver->resolve('system/tmp/missing.ics'));
        $this->assertNull($this->resolver->resolve('system/tmp'));
        $this->assertNull($this->resolver->resolve(''));
    }

    public function testRejectsSymlinkToFileOutsideOfUploadDirectory(): void
    {
        (new Filesystem())->symlink($this->projectDir.'/system/config/localconfig.ics', $this->projectDir.'/system/tmp/link.ics');

        $this->assertNull($this->resolver->resolve('system/tmp/link.ics'));
    }
}
