<?php

namespace Base\Admin\Settings;

use Base\Admin\Theme\AdminTheme;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The back office's two colours (Base\Admin\Theme\AdminTheme): plain text
 * fields holding #rrggbb - empty is the configured default - which the
 * settings page enhances with a colour picker and a live preview. Not a
 * colour input: one has no empty state and would save black for a site
 * that never chose.
 */
#[AsTaggedItem(priority: 99)]
final class ThemeSettingsSection implements SettingsSectionInterface
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly AdminTheme $theme)
    {
    }

    public function getPage(): string
    {
        return self::SETTINGS;
    }

    public function getFields(): array
    {
        $defaults = $this->theme->defaults();
        $field = fn (string $key) => [
            'required' => false,
            'label' => $this->translator->trans('page.settings.field.theme_'.$key, [], 'admin'),
            'help' => $this->translator->trans('page.settings.field.theme_'.$key.'_help', ['%default%' => $defaults[$key]], 'admin'),
        ];

        return [
            AdminTheme::PRIMARY => $field('primary'),
            AdminTheme::SECONDARY => $field('secondary'),
        ];
    }
}
