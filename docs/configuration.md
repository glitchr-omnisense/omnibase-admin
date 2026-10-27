---
title: Configuration reference
order: 40
---

# Configuration reference

## `admin:`

`Base\Admin\DependencyInjection\AdminConfiguration`, dumped in the core's harness
(`bin/console config:dump-reference admin`, this checkout mounted over
`vendor/omnibase/admin`):

```yaml
# Default configuration for extension with alias: "admin"
admin:

    # Readable identifiers used in admin URLs instead of the numeric id.
    url_identifier:

        # Identifier fields in priority order. Empty list = always use the numeric id.
        fields:

            # Defaults:
            - slug
            - uuid

        # Lowercase generated identifiers. Only safe where the database compares strings case-insensitively (MySQL _ci collation).
        lowercase:            false

        # Per-entity override, keyed by FQCN. Applies to subclasses too.
        entities:

            # Prototype
            class:                []

```

| Option | Type | Default | Effect |
|---|---|---|---|
| `url_identifier.fields` | list of property names | `[slug, uuid]` | The identifiers a record's address uses instead of its id, in priority order: the first the entity stores as a column whose value is neither blank nor all digits. `[]`: always the id |
| `url_identifier.lowercase` | bool | `false` | Lowercase the identifiers in the addresses; safe only where the database compares strings case-insensitively (MySQL's `_ci` collations) |
| `url_identifier.entities` | map of class => list | `{}` | Per-entity fields, by class (subclasses included) |

They are given to the core's `Base\Field\FieldValueResolver` (`$identifierFields`,
`$identifierFieldsByEntity`, `$lowercaseIdentifiers`), which both builds and
resolves the addresses. See [CRUD fields](crud-fields.md#a-records-address).

## What the bundle registers

Autoconfiguration (`AdminBundle::build()`):

| Interface or class | Tag |
|---|---|
| `Base\Admin\Controller\CrudControllerInterface` | `base.admin.crud_controller`, `controller.service_arguments` |
| `Base\Admin\Controller\AbstractDashboardController` | `base.admin.dashboard_controller` |
| `Base\Admin\Widget\DashboardWidgetTypeInterface` | `base.admin.dashboard_widget_type` |
| `Base\Admin\Widget\PaletteDashboardWidgetTypeInterface` | `base.admin.dashboard_widget_type.palette` (as well) |
| `Base\Admin\Settings\SettingsSectionInterface` | `base.admin.settings_section` |
| `Base\Service\TimelineEventProviderInterface` (the core's) | `base.admin.timeline_event_provider` |

`AdminRoutePass` collects the non-abstract services tagged
`base.admin.crud_controller` into `AdminRouteRegistry` (and a service locator for
`CrudAccessChecker`), and those tagged `base.admin.dashboard_controller` as the
dashboard.

Services it gives the core: `Base\Notifier\NotificationLinkerInterface` is
`Base\Admin\Notifier\AdminNotificationLinker` (the back-office page a
notification is about, `notification_admin_url()` in Twig). `Base\Field\FieldValueResolver`,
`FieldFormBuilder`, `AdminRouteRegistry`, `AdminRouteLoader` (tag `routing.loader`),
`AdminUrlGenerator`, `CrudAccessChecker` (reset between requests), `SecurityVoter`
(tag `security.voter`), `MenuBuilder`, `LayoutStore`, `LayoutArranger` and the
system controllers are defined in `config/services.php`.
