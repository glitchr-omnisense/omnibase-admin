<?php

namespace Tests\Base\Admin;

use Base\Admin\AdminBundle;
use Base\Admin\Test\CrudFieldsTestCase;
use PHPUnit\Framework\TestCase;

/**
 * glitchr/omnibase loads every class of a bundle's src/ when it boots (the
 * aliases of each first-level directory), in the console as on a page. A
 * class there that extends PHPUnit stops an application whose console has no
 * PHPUnit (symfony/phpunit-bridge installs it in var/cache/phpunit, read by
 * bin/phpunit alone): cache:clear --env=test failed on "Class
 * PHPUnit\Framework\TestCase not found" once a test run had recorded the
 * alias. What the bundles' tests extend lives in testing/.
 */
final class PackagingTest extends TestCase
{
    public function testNothingUnderSrcNeedsPhpunit(): void
    {
        $src = \dirname((new \ReflectionClass(AdminBundle::class))->getFileName());
        $offenders = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ('php' === $file->getExtension() && preg_match('/\bPHPUnit\\\\/', (string) file_get_contents($file->getPathname()))) {
                $offenders[] = substr($file->getPathname(), \strlen($src) + 1);
            }
        }

        $this->assertSame([], $offenders, 'A class of src/ names PHPUnit: an application booting without it cannot load it.');
    }

    public function testTheBundlesTestCaseIsOutsideSrc(): void
    {
        $file = (string) (new \ReflectionClass(CrudFieldsTestCase::class))->getFileName();

        $this->assertSame('testing', basename(\dirname($file)));
        $this->assertTrue(is_subclass_of(CrudFieldsTestCase::class, TestCase::class));
    }
}
