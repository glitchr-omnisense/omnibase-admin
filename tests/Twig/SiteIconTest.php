<?php

namespace Tests\Base\Admin\Twig;

use Base\Admin\Twig\SiteIconTwigExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The back office's tab shows the site's icon: its pages linked none, so the
 * browser asked for /favicon.ico, a 404 on the sites that keep their icons
 * under public/assets/ico/ alone (monica, juriste, notaire, omnisense,
 * latoucheoriginale...).
 */
class SiteIconTest extends TestCase
{
    private string $public;

    protected function setUp(): void
    {
        $this->public = sys_get_temp_dir().'/admin-site-icons-'.bin2hex(random_bytes(4));
        mkdir($this->public.'/assets/ico', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->public.'/{assets/ico/*,*.ico}', \GLOB_BRACE) ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->public.'/assets/ico');
        rmdir($this->public.'/assets');
        rmdir($this->public);
    }

    private function touch(string ...$files): void
    {
        foreach ($files as $file) {
            touch($this->public.'/'.$file);
        }
    }

    private function render(string $template): string
    {
        $twig = new Environment(new FilesystemLoader());
        $twig->getLoader()->addPath(\dirname(__DIR__, 2).'/templates', 'Admin');
        $twig->addExtension(new SiteIconTwigExtension($this->public));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path) => '/'.$path));

        return $twig->render($template);
    }

    public function testASiteWithASvgIconLinksIt(): void
    {
        $this->touch('assets/ico/favicon.svg');

        $html = $this->render('@Admin/_site_icons.html.twig');

        $this->assertStringContainsString('<link rel="icon" type="image/svg+xml" href="/assets/ico/favicon.svg">', $html);
        $this->assertSame(1, substr_count($html, '<link'));
    }

    public function testASiteWithTheUsualSetLinksEachIcon(): void
    {
        $this->touch('assets/ico/favicon-32x32.png', 'assets/ico/favicon-16x16.png', 'assets/ico/apple-touch-icon.png', 'assets/ico/favicon.ico', 'favicon.ico');

        $icons = (new SiteIconTwigExtension($this->public))->siteIcons();

        $this->assertSame([
            ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32', 'href' => 'assets/ico/favicon-32x32.png'],
            ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '16x16', 'href' => 'assets/ico/favicon-16x16.png'],
            ['rel' => 'apple-touch-icon', 'type' => null, 'sizes' => '180x180', 'href' => 'assets/ico/apple-touch-icon.png'],
            ['rel' => 'icon', 'type' => null, 'sizes' => 'any', 'href' => 'assets/ico/favicon.ico'],
        ], $icons);
    }

    public function testTheShortNamesOfTheMusiciansSitesAreRead(): void
    {
        $this->touch('assets/ico/favicon-32.png', 'assets/ico/favicon-16.png');

        $hrefs = array_column((new SiteIconTwigExtension($this->public))->siteIcons(), 'href');

        $this->assertSame(['assets/ico/favicon-32.png', 'assets/ico/favicon-16.png'], $hrefs);
    }

    public function testAnIconAtTheRootAloneIsLinked(): void
    {
        $this->touch('favicon.ico');

        $this->assertSame(['favicon.ico'], array_column((new SiteIconTwigExtension($this->public))->siteIcons(), 'href'));
    }

    public function testASiteWithoutIconAsksForNothing(): void
    {
        $html = $this->render('@Admin/_site_icons.html.twig');

        $this->assertStringContainsString('<link rel="icon" href="data:,">', $html);
        $this->assertStringNotContainsString('favicon.ico', $html);
    }

    public function testTheLayoutAndTheSignInPagePrintTheIcons(): void
    {
        foreach (['layout.html.twig', 'page/login.html.twig'] as $template) {
            $source = file_get_contents(\dirname(__DIR__, 2).'/templates/'.$template);
            $head = substr($source, 0, (int) strpos($source, '</head>'));

            $this->assertStringContainsString("include('@Admin/_site_icons.html.twig')", $head, $template);
        }
    }
}
