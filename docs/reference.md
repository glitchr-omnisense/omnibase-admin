---
title: Reference
order: 45
---

# Reference

## Routes

Under the prefix `/admin`, from the `base_admin` loader (`bin/console debug:router`
in the core's harness). The CRUDs' routes are in
[Writing a CRUD](crud-controllers.md#routes-and-addresses).

| Route | Path | Methods | Controller |
|---|---|---|---|
| `admin` | `/admin` | GET | the dashboard controller's `index` (when it has no `#[Route]` of its own) |
| `admin_settings` | `/admin/settings` | GET, POST | `SystemController::settings` |
| `admin_apikey` | `/admin/api-key` | GET, POST | `SystemController::apiKey` |
| `admin_settings_quick` | `/admin/settings/quick` | POST | `SystemController::settingsQuick` |
| `admin_layout_save` | `/admin/layout/{scope}` | POST | `LayoutController::save` |
| `admin_layout_quick` | `/admin/layout/{scope}/quick` | POST | `LayoutController::quickSave` |
| `admin_icon_search` | `/admin/layout/icons` | GET | `LayoutController::icons` |
| `admin_analytics_breakdown` | `/admin/analytics/breakdown/{range}` | GET | `AnalyticsController::breakdown` |
| `admin_dashboard_widget_types` | `/admin/dashboard/widget-types` | GET | `DashboardWidgetController::types` |
| `admin_dashboard_widget_new` | `/admin/dashboard/widget/{blockName}/new` | GET | `DashboardWidgetController::newInstance` |
| `admin_dashboard_widget_instances` | `/admin/dashboard/widget/instances` | GET | `DashboardWidgetController::instances` |
| `admin_dashboard_widget_merge` | `/admin/dashboard/widget/merge` | GET | `DashboardWidgetController::merge` |
| `admin_dashboard_widget_split` | `/admin/dashboard/widget/split` | GET | `DashboardWidgetController::split` |
| `admin_dashboard_widget_restore` | `/admin/dashboard/widget/restore` | POST | `DashboardWidgetController::restore` |
| `admin_revision_value` | `/admin/revision/{id}` | GET | `RevisionController::value` |
| `admin_trash` | `/admin/trash` | GET | `TrashController::index` |
| `admin_trash_restore` | `/admin/trash/{id}/restore` | POST | `TrashController::restore` |
| `admin_trash_destroy` | `/admin/trash/{id}/destroy` | POST | `TrashController::destroy` |
| `admin_trash_empty` | `/admin/trash/empty` | POST | `TrashController::empty` |
| `admin_hours` | `/admin/hours` | GET | `HoursController::index` |
| `admin_hours_week` | `/admin/hours/week` | POST | `HoursController::week` |
| `admin_hours_special` | `/admin/hours/special` | POST | `HoursController::addSpecialDay` |
| `admin_hours_special_delete` | `/admin/hours/special/{id}/delete` | POST | `HoursController::deleteSpecialDay` |

The controllers are `Base\Admin\Controller\`'s. The trash lists the core's
`TrashBall`s (what was deleted from a trasheable entity), restores one or
destroys it; the revisions page gives an older value of a versioned field.

## Twig

| Function | What it gives |
|---|---|
| `admin_url(controller, action = 'index', entityId = null, parameters = {})` | A CRUD page's address |
| `admin_action_url(action, entity, controller)` | An action's address for a row: its URL callable, its static URL, else its CRUD action's route |
| `admin_action_post(action, controller)` | Whether the action runs an `#[AdminAction]` route that takes no GET (drawn as a form posting its token) |
| `admin_action_token_id(method)` | `admin-action-<method>` |
| `admin_action_order(actions, stored = [])` | The actions in the order stored |
| `admin_context()` | The `AdminContext` of the page, built from the default menus for a screen that passed none |
| `admin_menu_current()` | The selected menu item (the longest prefix of the current path) |
| `admin_crud_granted(controller)` | Whether the user may open that CRUD (its entity permission) |
| `admin_entity_crud(entity)` | The CRUD that manages a record's class, or `null` |
| `admin_entity_id(entity)` | Its address identifier |
| `admin_entity_label(entity)`, `admin_entity_avatar(entity)`, `admin_entity_image(entity)` | Its label, avatar, image (`FieldValueResolver`) |
| `admin_entity_icon(entityOrClass)` | Its CRUD's icon |
| `admin_entity_name(class, plural = false)` | The entity's name: its translation in `entities`, else its class name in words |
| `admin_display(...)` | A field's value formatted (`FieldValueResolver::formatValue()`) |
| `admin_enum_label(value, enumClass = null)` | An enum case's label |
| `admin_field_choices(field)` | A field's choices (empty for choices made by a closure) |
| `admin_site_icons()` | The site's own icons for the back office's tab (`@Admin/_site_icons.html.twig`) |
| `admin_dev_version()` | In debug, a suffix of transparentJS's cache version that changes with uncommitted edits of the vendors |
| `dashboard_widget_type(name)` | A widget type |

Global: `admin_active_users`, on the back office's requests: the other
administrators active within `User::getOnlineDelay()`, the most recent first.

## Listeners

| Event | Listener | Priority | Effect |
|---|---|---|---|
| `kernel.request` | `ActiveAdminsSubscriber::onKernelRequest` | 4 | Sets `admin_active_users` |
| `kernel.controller` | `AdminActionSubscriber::onKernelController` | 0 | Runs the CRUD's gate before an `#[AdminAction]` method |
| `kernel.response` | `NestHeaderSubscriber::onKernelResponse` | 0 | Adds `X-Transparent-Nest` to the back office's pages (path under `/admin`, route `admin` or `admin_*`) and to routes with `defaults: {_nest: true}` |

## The voter

`Base\Admin\Security\SecurityVoter` decides the back office's attributes
(`Base\Admin\Security\Permission`):

| Attribute | Subject | Granted when |
|---|---|---|
| `EA_VIEW_MENU_ITEM` | a menu item | it has no permission, or its permission is granted |
| `EA_EXECUTE_ACTION` | an action (and the record) | the same, with the action's permission on the record |
| `EA_VIEW_FIELD` | a field | the same, with the field's permission on its value |
| `EA_ACCESS_ENTITY` | `{permission, entity}` | the same |
| `EA_EXIT_IMPERSONATION` | | `IS_IMPERSONATOR` |

A permission is any attribute: a role, or one of the core's permission tags
(`PermissionVoter`, `ARTICLE.PUBLISH`).
