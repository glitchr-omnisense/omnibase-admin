<?php

namespace Base\Admin\Twig;

use Base\Admin\Theme\AdminTheme;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** admin_theme_css(): the colour tokens derived from the site's two colours, for the layout's <style id="admin-theme">. */
final class AdminThemeTwigExtension extends AbstractExtension
{
    public function __construct(private readonly AdminTheme $theme)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_theme_css', fn (): string => $this->theme->palette()->css(), ['is_safe' => ['html']]),
            new TwigFunction('admin_theme_colors', fn (): array => $this->theme->colors()),
            new TwigFunction('admin_theme_defaults', fn (): array => $this->theme->defaults()),
        ];
    }
}
