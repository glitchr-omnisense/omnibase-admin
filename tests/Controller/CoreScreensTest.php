<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Enum\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * The screens of the core's own entities live in this bundle
 * (Base\Admin\Controller\Crud\*): each extends AbstractCrudController, keeps
 * its slug, so its routes and addresses, and is found by its entity's name.
 */
final class CoreScreensTest extends TestCase
{
    private const SCREENS = [
        'UserCrudController' => 'users',
        'User\ConnectionCrudController' => 'users/connections',
        'User\GroupCrudController' => 'users/groups',
        'User\PermissionCrudController' => 'users/permissions',
        'Thread\TagCrudController' => 'threads/tags',
        'Thread\TaxonCrudController' => 'threads/taxons',
        'Layout\ImageCrudController' => 'layouts/images',
        'Layout\SettingCrudController' => 'layouts/settings',
        'Layout\Attribute\AdapterCrudController' => 'layouts/attributes/adapters',
        'Layout\Widget\PageCrudController' => 'layouts/widgets/pages',
    ];

    public function testEachScreenIsACrudController(): void
    {
        foreach (array_keys(self::SCREENS) as $name) {
            $screen = 'Base\\Admin\\Controller\\Crud\\'.$name;

            self::assertTrue(class_exists($screen), $screen);
            self::assertContains(AbstractCrudController::class, class_parents($screen), $screen);
        }
    }

    public function testTheSlugs(): void
    {
        foreach (self::SCREENS as $name => $slug) {
            self::assertSame($slug, AdminRouteRegistry::slugify('Base\\Admin\\Controller\\Crud\\'.$name));
        }
    }

    public function testTheEntityFindsItsScreenByConvention(): void
    {
        self::assertSame('Base\\Admin\\Controller\\Crud\\User\\GroupCrudController', AbstractCrudController::getCrudControllerFqcn('Base\\Entity\\User\\Group'));
        self::assertSame('Base\\Admin\\Controller\\Crud\\UserCrudController', UserRole::getCrudController(UserRole::ADMIN));
    }

    public function testTheCoresSwappedNameOfAScreenIsNotTakenForTheScreen(): void
    {
        // The core's BaseBundle declares each class of this bundle under a swapped name too
        // (Base\Admin\Controller\X also as Base\Controller\Admin\X): the "\Controller\Admin\Crud\"
        // candidate found it first, and the tags field of a blog post's form linked to a screen
        // AdminRouteRegistry does not know (500 on /admin/posts/new).
        $swapped = 'Base\\Controller\\Admin\\Crud\\Thread\\TagCrudController';
        if (!class_exists($swapped, false)) {
            class_alias('Base\\Admin\\Controller\\Crud\\Thread\\TagCrudController', $swapped);
        }

        self::assertSame('Base\\Admin\\Controller\\Crud\\Thread\\TagCrudController', AbstractCrudController::getCrudControllerFqcn('Base\\Entity\\Thread\\Tag'));
    }
}
