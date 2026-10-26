<?php

/*
 * The former names of the back office's screens that moved from the core
 * (omnisense/omnibase 4.0, Base\Controller\Backoffice\Crud\*) to this bundle
 * (Base\Admin\Controller\Crud\*): they extend this bundle's
 * AbstractCrudController, which the core alone does not have - a dependency in
 * the wrong direction, which the core's CI (its package job, PHPStan) showed.
 *
 * Each former name is the moved class under its former name, a class_alias
 * made when it is first asked for (an application's controller extending
 * Base\Controller\Backoffice\Crud\UserCrudController, a role's
 * getCrudController()), not before: loading them all with the autoloader would
 * load the back office on every request. Same screens, same slugs, same routes
 * (AdminRouteRegistry names them by what follows "Crud\").
 *
 * Kept for one release (omnibase/admin's next); an application names the new
 * classes meanwhile.
 */

spl_autoload_register(static function (string $class): void {
    static $moved = [
        'Base\Controller\Backoffice\Crud\UserCrudController' => 'Base\Admin\Controller\Crud\UserCrudController',
        'Base\Controller\Backoffice\Crud\User\ConnectionCrudController' => 'Base\Admin\Controller\Crud\User\ConnectionCrudController',
        'Base\Controller\Backoffice\Crud\User\GroupCrudController' => 'Base\Admin\Controller\Crud\User\GroupCrudController',
        'Base\Controller\Backoffice\Crud\User\PermissionCrudController' => 'Base\Admin\Controller\Crud\User\PermissionCrudController',
        'Base\Controller\Backoffice\Crud\Thread\TagCrudController' => 'Base\Admin\Controller\Crud\Thread\TagCrudController',
        'Base\Controller\Backoffice\Crud\Thread\TaxonCrudController' => 'Base\Admin\Controller\Crud\Thread\TaxonCrudController',
        'Base\Controller\Backoffice\Crud\Layout\ImageCrudController' => 'Base\Admin\Controller\Crud\Layout\ImageCrudController',
        'Base\Controller\Backoffice\Crud\Layout\SettingCrudController' => 'Base\Admin\Controller\Crud\Layout\SettingCrudController',
        'Base\Controller\Backoffice\Crud\Layout\Attribute\AdapterCrudController' => 'Base\Admin\Controller\Crud\Layout\Attribute\AdapterCrudController',
        'Base\Controller\Backoffice\Crud\Layout\Widget\PageCrudController' => 'Base\Admin\Controller\Crud\Layout\Widget\PageCrudController',
    ];

    if (isset($moved[$class]) && class_exists($moved[$class])) {
        @trigger_error(sprintf('Since omnibase/admin (omnisense/omnibase 4.0): "%s" is "%s".', $class, $moved[$class]), \E_USER_DEPRECATED);
        class_alias($moved[$class], $class);
    }
});
