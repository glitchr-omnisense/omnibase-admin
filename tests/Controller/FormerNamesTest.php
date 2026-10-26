<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Enum\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * The core's screens moved here (omnisense/omnibase 4.0): their former names,
 * Base\Controller\Backoffice\Crud\*, are the moved classes themselves for one
 * release (src/Resources/former-names.php) - an alias, not a subclass - and the
 * screens keep their slugs, so their routes and addresses.
 */
final class FormerNamesTest extends TestCase
{
    private const MOVED = [
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

    public function testEachFormerNameIsTheMovedClass(): void
    {
        foreach (array_keys(self::MOVED) as $name) {
            $former = 'Base\\Controller\\Backoffice\\Crud\\'.$name;
            $moved = 'Base\\Admin\\Controller\\Crud\\'.$name;

            self::assertTrue(class_exists($moved), $moved);
            self::assertContains(AbstractCrudController::class, class_parents($moved), $moved);
            @self::assertTrue(class_exists($former), $former);
            self::assertSame($moved, (new \ReflectionClass($former))->getName(), $former.' is an alias of '.$moved);
        }
    }

    public function testTheSlugsAreThoseOfBefore(): void
    {
        foreach (self::MOVED as $name => $slug) {
            self::assertSame($slug, AdminRouteRegistry::slugify('Base\\Admin\\Controller\\Crud\\'.$name));
            self::assertSame($slug, AdminRouteRegistry::slugify('Base\\Controller\\Backoffice\\Crud\\'.$name));
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
