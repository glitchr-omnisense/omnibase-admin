<?php

namespace Base\Admin\Theme;

use Base\Service\SettingBagInterface;

/**
 * The back office's two colours for this site: the settings page's
 * admin.theme.primary / admin.theme.secondary when set, else the bundle's
 * configuration (admin.theme in config/packages/admin.yaml), else the
 * defaults (Palette::DEFAULT_*). Every --admin-* colour token derives from
 * them (Palette), light and dark, kept readable.
 */
class AdminTheme
{
    public const PRIMARY = 'admin.theme.primary';
    public const SECONDARY = 'admin.theme.secondary';

    private ?Palette $palette = null;

    public function __construct(
        private readonly ?SettingBagInterface $settings = null,
        private readonly string $primary = Palette::DEFAULT_PRIMARY,
        private readonly string $secondary = Palette::DEFAULT_SECONDARY,
    ) {
    }

    /** The configured default pair (configuration, else the bundle's). @return array{primary: string, secondary: string} */
    public function defaults(): array
    {
        return [
            'primary' => Palette::normalize($this->primary) ?? Palette::DEFAULT_PRIMARY,
            'secondary' => Palette::normalize($this->secondary) ?? Palette::DEFAULT_SECONDARY,
        ];
    }

    /** @return array{primary: string, secondary: string} the pair in use */
    public function colors(): array
    {
        $defaults = $this->defaults();

        return [
            'primary' => Palette::normalize($this->stored(self::PRIMARY)) ?? $defaults['primary'],
            'secondary' => Palette::normalize($this->stored(self::SECONDARY)) ?? $defaults['secondary'],
        ];
    }

    public function palette(): Palette
    {
        if (null === $this->palette) {
            $colors = $this->colors();
            $this->palette = Palette::derive($colors['primary'], $colors['secondary']);
        }

        return $this->palette;
    }

    /** A pair tried on the settings page before it is saved: an invalid colour falls back to the one in use. */
    public function preview(?string $primary, ?string $secondary): Palette
    {
        $colors = $this->colors();

        return Palette::derive(Palette::normalize($primary) ?? $colors['primary'], Palette::normalize($secondary) ?? $colors['secondary']);
    }

    private function stored(string $path): ?string
    {
        if (null === $this->settings) {
            return null;
        }
        try {
            $value = $this->settings->getScalar($path);
        } catch (\Throwable) {
            return null;   // no settings table yet
        }

        return \is_string($value) ? $value : null;
    }
}
