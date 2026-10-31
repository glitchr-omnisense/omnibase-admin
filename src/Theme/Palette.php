<?php

namespace Base\Admin\Theme;

/**
 * The back office's colour tokens derived from two colours: the primary (the
 * accent - links, buttons, focus rings, the active menu entry, the first
 * chart series, the faint tint of the page) and the secondary (the ink -
 * text, muted text, borders, the second chart series). Light and dark.
 *
 * Every pair stays readable: a text colour is moved towards black (light)
 * or white (dark) until it reaches WCAG AA against what it sits on - 4.5:1
 * for text, 3:1 for a chart mark or a focus ring - and the text drawn on a
 * button picks whichever of white and the ink reads better. Each colour so
 * moved is listed in notes(), for the settings page to say so.
 */
final class Palette
{
    public const DEFAULT_PRIMARY = '#3a47d5';
    public const DEFAULT_SECONDARY = '#1b2140';

    /** @var array<string, string> */
    private array $light = [];

    /** @var array<string, string> */
    private array $dark = [];

    /** @var list<string> */
    private array $notes = [];

    private function __construct(public readonly string $primary, public readonly string $secondary)
    {
    }

    /** A colour as #rrggbb, or null when it is not one (#rgb is widened). */
    public static function normalize(?string $color): ?string
    {
        $color = strtolower(trim((string) $color));
        if (preg_match('/^#?([0-9a-f])([0-9a-f])([0-9a-f])$/', $color, $m)) {
            return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
        }

        return preg_match('/^#?([0-9a-f]{6})$/', $color, $m) ? '#'.$m[1] : null;
    }

    public static function derive(?string $primary = null, ?string $secondary = null): self
    {
        $palette = new self(self::normalize($primary) ?? self::DEFAULT_PRIMARY, self::normalize($secondary) ?? self::DEFAULT_SECONDARY);
        $palette->build();

        return $palette;
    }

    /** @return array<string, string> custom property (without --admin-) => value, light theme */
    public function light(): array
    {
        return $this->light;
    }

    /** @return array<string, string> the same, dark theme */
    public function dark(): array
    {
        return $this->dark;
    }

    /** @return list<string> the light theme's tokens whose colour was moved for contrast (text, accent, series-2...) */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * The tokens as CSS: :root for the light theme, the dark one under
     * prefers-color-scheme (unless the light theme was chosen) and under
     * [data-theme="dark"], as the layout declares its own.
     */
    public function css(): string
    {
        $block = static fn (array $tokens): string => implode(' ', array_map(static fn (string $k, string $v): string => '--admin-'.$k.': '.$v.';', array_keys($tokens), $tokens));

        return ':root { '.$block($this->light).' }'."\n"
            .'@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) { '.$block($this->dark).' } }'."\n"
            .':root[data-theme="dark"] { '.$block($this->dark).' }';
    }

    private function build(): void
    {
        $p = self::rgb($this->primary);
        $s = self::rgb($this->secondary);
        $white = [255, 255, 255];

        // Light: white cards on a page faintly tinted by the primary.
        $surface = $white;
        $bg = self::mix($p, $white, .055);
        $text = $this->reach('text', 'light', $s, $bg, 7.0, [0, 0, 0]);
        $accent = $this->reach('accent', 'light', $p, $surface, 4.5, [0, 0, 0]);
        $accent = $this->reach('accent', 'light', $accent, $bg, 4.5, [0, 0, 0]);
        $this->light = [
            'bg' => self::hex($bg),
            'surface' => self::hex($surface),
            'border' => self::hex(self::mix($text, $surface, .12)),
            'text' => self::hex($text),
            'muted' => self::hex(self::fade($text, $bg, 5.2)),
            'accent' => self::hex($accent),
            'on-accent' => self::hex(self::onColor($accent, $text)),
            'accent-soft' => self::hex(self::mix($accent, $surface, .12)),
            'tint' => self::hex(self::mix($p, $white, .06)),
            'tint-strong' => self::hex(self::mix($p, $white, .11)),
            'focus' => self::hex($this->reach('focus', 'light', $accent, $surface, 3.0, [0, 0, 0])),
            'series-1' => self::hex($accent),
            'series-2' => self::hex($this->series2($s, $accent, $surface, [0, 0, 0], 'light')),
        ];

        // Dark: a page and cards of the secondary's hue, always dark whatever
        // its lightness (a light secondary still gives a dark page).
        [$h, $sat] = self::hsl($s);
        $bg = self::fromHsl($h, min($sat, .32), .085);
        $surface = self::fromHsl($h, min($sat, .30), .13);
        $text = $this->reach('text', 'dark', self::fromHsl($h, min($sat, .25), .94), $bg, 12.0, $white);
        $accent = $this->reach('accent', 'dark', $p, $surface, 6.0, $white);
        $this->dark = [
            'bg' => self::hex($bg),
            'surface' => self::hex($surface),
            'border' => self::hex(self::mix($white, $surface, .09)),
            'text' => self::hex($text),
            'muted' => self::hex(self::fade($text, $surface, 5.6)),
            'accent' => self::hex($accent),
            'on-accent' => self::hex(self::onColor($accent, $bg)),
            'accent-soft' => self::hex(self::mix($accent, $surface, .18)),
            'tint' => self::hex(self::mix($p, $surface, .10)),
            'tint-strong' => self::hex(self::mix($p, $surface, .16)),
            'focus' => self::hex($this->reach('focus', 'dark', $accent, $surface, 3.0, $white)),
            'series-1' => self::hex($accent),
            'series-2' => self::hex($this->series2(self::fromHsl($h, min($sat, .45), .7), $accent, $surface, $white, 'dark')),
        ];
    }

    /** The secondary as a chart mark: 3:1 against the card, and apart from the first series. */
    private function series2(array $color, array $accent, array $surface, array $towards, string $theme): array
    {
        $color = $this->reach('series-2', $theme, $color, $surface, 3.0, $towards);
        if (self::contrast($color, $accent) < 1.4) {
            // Too close to the primary to tell apart: the other end of the scale.
            $color = self::mix($color, 'light' === $theme ? [255, 255, 255] : [0, 0, 0], .45);
            $color = $this->reach('series-2', $theme, $color, $surface, 3.0, $towards);
        }

        return $color;
    }

    /** $color moved towards $towards until it reaches $ratio against $against; noted when moved. */
    private function reach(string $token, string $theme, array $color, array $against, float $ratio, array $towards): array
    {
        if (self::contrast($color, $against) >= $ratio) {
            return $color;
        }
        for ($t = .02; $t <= 1.0; $t += .02) {
            $moved = self::mix($towards, $color, $t);
            if (self::contrast($moved, $against) >= $ratio) {
                $this->note($token, $theme);

                return $moved;
            }
        }
        $this->note($token, $theme);

        return $towards;
    }

    /**
     * A colour of the light theme moved for contrast: what the settings page
     * tells (the dark theme always lightens the colours, by design).
     */
    private function note(string $token, string $theme): void
    {
        if ('light' === $theme && !\in_array($token, $this->notes, true)) {
            $this->notes[] = $token;
        }
    }

    /** @return array{float, float, float} hue (0-1), saturation, lightness */
    private static function hsl(array $rgb): array
    {
        [$r, $g, $b] = [$rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $sat = $l > .5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $sat, $l];
    }

    /** @return array{float, float, float} */
    private static function fromHsl(float $h, float $sat, float $l): array
    {
        if (0.0 === $sat) {
            return [$l * 255, $l * 255, $l * 255];
        }
        $q = $l < .5 ? $l * (1 + $sat) : $l + $sat - $l * $sat;
        $p = 2 * $l - $q;
        $channel = static function (float $t) use ($p, $q): float {
            $t += $t < 0 ? 1 : ($t > 1 ? -1 : 0);

            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };

        return [$channel($h + 1 / 3) * 255, $channel($h) * 255, $channel($h - 1 / 3) * 255];
    }

    /** $color faded towards $against as far as it still keeps $ratio: a muted text. */
    private static function fade(array $color, array $against, float $ratio): array
    {
        $best = $color;
        for ($t = .02; $t < 1.0; $t += .02) {
            $faded = self::mix($against, $color, $t);
            if (self::contrast($faded, $against) < $ratio) {
                break;
            }
            $best = $faded;
        }

        return $best;
    }

    /** White or the ink on a filled $color, whichever reads better (white wins a tie at AA). */
    private static function onColor(array $color, array $ink): array
    {
        $white = [255, 255, 255];

        return self::contrast($white, $color) >= 4.5 || self::contrast($white, $color) >= self::contrast($ink, $color) ? $white : $ink;
    }

    /** WCAG 2 contrast ratio. */
    public static function contrast(array $a, array $b): float
    {
        [$la, $lb] = [self::luminance($a), self::luminance($b)];

        return (max($la, $lb) + .05) / (min($la, $lb) + .05);
    }

    public static function luminance(array $rgb): float
    {
        $c = array_map(static function (float $v): float {
            $v /= 255;

            return $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
        }, $rgb);

        return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2];
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim((string) self::normalize($hex), '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    public static function hex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(static fn (float $v): int => (int) max(0, min(255, round($v))), $rgb));
    }

    /** $a weighted $weight over $b. */
    private static function mix(array $a, array $b, float $weight): array
    {
        return [$a[0] * $weight + $b[0] * (1 - $weight), $a[1] * $weight + $b[1] * (1 - $weight), $a[2] * $weight + $b[2] * (1 - $weight)];
    }
}
