<?php

namespace Tests\Base\Admin\Theme;

use Base\Admin\Theme\AdminTheme;
use Base\Admin\Theme\Palette;
use Base\Admin\Settings\ThemeSettingsSection;
use Base\Service\SettingBagInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The back office's colour tokens derived from a site's two colours, always readable. */
class PaletteTest extends TestCase
{
    public static function pairs(): iterable
    {
        yield 'the defaults' => [null, null];
        yield 'green and brown' => ['#2e7d32', '#4e342e'];
        yield 'a yellow and white (both unreadable on white)' => ['#ffd600', '#ffffff'];
        yield 'pink and slate' => ['#e91e63', '#263238'];
        yield 'black and black' => ['#000', '#000000'];
        yield 'a light secondary' => ['#1565c0', '#cfd8dc'];
    }

    /** @dataProvider pairs */
    #[DataProvider('pairs')]
    public function testEveryTextAndMarkReachesWcagAaInBothThemes(?string $primary, ?string $secondary): void
    {
        $palette = Palette::derive($primary, $secondary);
        foreach (['light' => $palette->light(), 'dark' => $palette->dark()] as $theme => $t) {
            $c = static fn (string $a, string $b): float => Palette::contrast(Palette::rgb($t[$a]), Palette::rgb($t[$b]));
            $this->assertGreaterThanOrEqual(7.0, $c('text', 'bg'), "$theme text on the page");
            $this->assertGreaterThanOrEqual(4.5, $c('muted', 'surface'), "$theme muted text on a card");
            $this->assertGreaterThanOrEqual(4.5, $c('accent', 'surface'), "$theme accent (links) on a card");
            $this->assertGreaterThanOrEqual(4.5, $c('on-accent', 'accent'), "$theme text on a filled button");
            $this->assertGreaterThanOrEqual(3.0, $c('focus', 'surface'), "$theme focus ring");
            $this->assertGreaterThanOrEqual(3.0, $c('series-2', 'surface'), "$theme second chart series");
            $this->assertGreaterThanOrEqual(4.5, $c('text', 'tint-strong'), "$theme text on a tinted row");
        }
        // The dark theme is dark whatever the secondary.
        $this->assertLessThan(.03, Palette::luminance(Palette::rgb($palette->dark()['bg'])));
    }

    public function testTheDefaultsKeepTheirLookAndAreNotAdjusted(): void
    {
        $palette = Palette::derive();
        $this->assertSame([], $palette->notes());
        $this->assertSame('#3a47d5', $palette->light()['accent']);
        $this->assertSame('#1b2140', $palette->light()['text']);
        $this->assertSame('#ffffff', $palette->light()['on-accent']);
    }

    public function testAColourMovedForContrastIsSaid(): void
    {
        $palette = Palette::derive('#ffd600', '#ffffff');
        $this->assertContains('accent', $palette->notes());
        $this->assertContains('text', $palette->notes());
        $this->assertNotSame('#ffd600', $palette->light()['accent'], 'darkened on white');
        $this->assertSame('#ffd600', $palette->dark()['accent'], 'kept on the dark page');
    }

    public function testColoursAreNormalizedAndAnInvalidOneIsTheDefault(): void
    {
        $this->assertSame('#aabbcc', Palette::normalize('ABC'));
        $this->assertSame('#2e7d32', Palette::normalize(' #2E7D32 '));
        $this->assertNull(Palette::normalize('red'));
        $this->assertNull(Palette::normalize('#12345'));
        $this->assertSame(Palette::DEFAULT_PRIMARY, Palette::derive('nope', null)->primary);
    }

    public function testTheCssCarriesBothThemes(): void
    {
        $css = Palette::derive('#2e7d32', '#4e342e')->css();
        $this->assertStringContainsString(':root { --admin-bg:', $css);
        $this->assertStringContainsString('--admin-accent: #2e7d32;', $css);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark) { :root:not([data-theme="light"])', $css);
        $this->assertStringContainsString(':root[data-theme="dark"] {', $css);
        $this->assertStringNotContainsString('<', $css, 'nothing that could close the <style>');
    }

    public function testTheSettingsWinOverTheConfigurationWhichWinsOverTheDefaults(): void
    {
        $settings = $this->createMock(SettingBagInterface::class);
        $stored = [AdminTheme::PRIMARY => '#2e7d32', AdminTheme::SECONDARY => ''];
        $settings->method('getScalar')->willReturnCallback(static fn (string $path) => $stored[$path] ?? null);

        $theme = new AdminTheme($settings, '#c62828', '#37474f');
        $this->assertSame(['primary' => '#2e7d32', 'secondary' => '#37474f'], $theme->colors(), 'an empty setting is the configuration');
        $this->assertSame(['primary' => '#c62828', 'secondary' => '#37474f'], $theme->defaults());
        $this->assertSame('#2e7d32', $theme->palette()->light()['accent']);

        $this->assertSame(['primary' => Palette::DEFAULT_PRIMARY, 'secondary' => Palette::DEFAULT_SECONDARY], (new AdminTheme(null))->colors());
        $this->assertSame('#e91e63', $theme->preview('#e91e63', 'not a colour')->primary);
        $this->assertSame('#37474f', $theme->preview('#e91e63', 'not a colour')->secondary, 'an invalid colour tried is the one in use');
    }

    public function testASettingsTableNotThereYetIsTheDefault(): void
    {
        $settings = $this->createMock(SettingBagInterface::class);
        $settings->method('getScalar')->willThrowException(new \RuntimeException('no table'));
        $this->assertSame(Palette::DEFAULT_PRIMARY, (new AdminTheme($settings))->colors()['primary']);
    }

    public function testTheSettingsPageOffersBothColoursWithTheirDefault(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters) => $id.json_encode($parameters));
        $fields = (new ThemeSettingsSection($translator, new AdminTheme(null, '#c62828')))->getFields();

        $this->assertSame([AdminTheme::PRIMARY, AdminTheme::SECONDARY], array_keys($fields));
        $this->assertFalse($fields[AdminTheme::PRIMARY]['required']);
        $this->assertStringContainsString('#c62828', $fields[AdminTheme::PRIMARY]['help']);
        $this->assertArrayNotHasKey('form_type', $fields[AdminTheme::PRIMARY], 'a text field: a colour input cannot be empty');
    }
}
