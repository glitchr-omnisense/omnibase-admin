<?php

namespace Base\Admin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The site's own icon in the back office's tab: admin_site_icons(), printed
 * by the layout and the sign-in page (@Admin/_site_icons.html.twig).
 *
 * The back office's pages linked no icon, so the browser asked for
 * /favicon.ico, which most sites do not serve (their icons live under
 * public/assets/ico/, linked by their own base template): a 404 on every
 * screen of /admin and a blank tab. The icons are read where the sites keep
 * them - public/assets/ico/ (favicon.svg, favicon-32x32.png or
 * favicon-32.png, the 16 px one, apple-touch-icon.png, favicon.ico), then
 * public/favicon.ico. A site with none gets an empty data: icon, so that the
 * browser asks for nothing.
 */
class SiteIconTwigExtension extends AbstractExtension
{
    public const DIRECTORY = 'assets/ico';

    /** @var list<array{rel: string, type: ?string, sizes: ?string, files: list<string>}> */
    private const CANDIDATES = [
        ['rel' => 'icon', 'type' => 'image/svg+xml', 'sizes' => null, 'files' => ['favicon.svg']],
        ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '32x32', 'files' => ['favicon-32x32.png', 'favicon-32.png']],
        ['rel' => 'icon', 'type' => 'image/png', 'sizes' => '16x16', 'files' => ['favicon-16x16.png', 'favicon-16.png']],
        ['rel' => 'apple-touch-icon', 'type' => null, 'sizes' => '180x180', 'files' => ['apple-touch-icon.png']],
    ];

    /** @var list<array{rel: string, type: ?string, sizes: ?string, href: string}>|null */
    private ?array $icons = null;

    public function __construct(private readonly string $publicDir)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_site_icons', $this->siteIcons(...)),
        ];
    }

    /**
     * The links to print, in order: their href is a path under public/, for
     * asset(), or a data: URI when the site serves no icon.
     *
     * @return list<array{rel: string, type: ?string, sizes: ?string, href: string}>
     */
    public function siteIcons(): array
    {
        if (null !== $this->icons) {
            return $this->icons;
        }

        $icons = [];
        foreach (self::CANDIDATES as $candidate) {
            foreach ($candidate['files'] as $file) {
                if (is_file($this->publicDir.'/'.self::DIRECTORY.'/'.$file)) {
                    $icons[] = ['rel' => $candidate['rel'], 'type' => $candidate['type'], 'sizes' => $candidate['sizes'], 'href' => self::DIRECTORY.'/'.$file];
                    break;
                }
            }
        }

        foreach ([self::DIRECTORY.'/favicon.ico', 'favicon.ico'] as $file) {
            if (is_file($this->publicDir.'/'.$file)) {
                $icons[] = ['rel' => 'icon', 'type' => null, 'sizes' => 'any', 'href' => $file];
                break;
            }
        }

        if ([] === $icons) {
            $icons[] = ['rel' => 'icon', 'type' => null, 'sizes' => null, 'href' => 'data:,'];
        }

        return $this->icons = $icons;
    }
}
