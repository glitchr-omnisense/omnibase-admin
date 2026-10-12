<?php

namespace Tests\Base\Admin\Assets;

use PHPUnit\Framework\TestCase;

/**
 * The back office's navigation and sortable are @glitchr/flowjs's own files, copied as they are
 * (public/js/README.md): the README names the commit, the library's code says its versions, and
 * the core (Flow) is there for transparent.js and the sortable to need - the navigation at least
 * at 3.0.31, which keeps the page's canvases going past one without an id (omnibase/music's
 * player wave stopped the others being kept).
 */
final class VendoredTransparentTest extends TestCase
{
    public function testTheCopyIsOneVersionOfTheLibraryNamedEverywhere(): void
    {
        $root = \dirname(__DIR__, 2);
        $core = file_get_contents($root.'/public/js/core.js');
        $js = file_get_contents($root.'/public/js/transparent.js');
        $sortable = file_get_contents($root.'/public/js/sticky-sortable.js');
        $css = file_get_contents($root.'/public/css/transparent.css');
        $readme = file_get_contents($root.'/public/js/README.md');

        self::assertSame(1, preg_match("~Flow = \\{ core: true, version: '(\\d+\\.\\d+\\.\\d+)' \\}~", $core, $m), 'the core says its version');
        $flow = $m[1];
        self::assertSame(1, preg_match('~Currently vendored at flowjs (\\d+\\.\\d+\\.\\d+) \\(glitchr-studio/flowjs ([0-9a-f]{40})\\)~', $readme, $m), 'the README names the version and the commit');
        self::assertSame($flow, $m[1], 'the README and the core name the same version');

        self::assertSame(1, preg_match("~Transparent\\.version = '(\\d+\\.\\d+\\.\\d+)';~", $js, $m), 'the navigation says its version');
        self::assertTrue(version_compare($m[1], '3.0.31', '>='), 'a canvas without an id must not stop the others being kept');
        self::assertStringNotContainsString('Unexpected canvas without ID found', $js);
        self::assertStringContainsString('StickySortable', $sortable);
        self::assertStringContainsString('progress-bar', $css);

        $layout = file_get_contents($root.'/templates/layout.html.twig');
        self::assertLessThan(strpos($layout, 'js/transparent.js'), strpos($layout, 'js/core.js'), 'the core is loaded before the navigation');
    }
}
