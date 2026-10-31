---
title: The dashboard and the menu
order: 25
---

# The dashboard and the menu

## The dashboard controller's hooks

`Base\Admin\Controller\AbstractDashboardController::index()` renders
`@Admin/dashboard.html.twig` with the widgets the hooks give, through
`MenuBuilder` (each item's visibility checked, the stored customisation applied):

| Hook | Default | What it gives |
|---|---|---|
| `configureWidgetItems()` | `[]` | The dashboard's groups: sections (or submenus) with their links, and blocks. Without any, the dashboard falls back to one card per registered CRUD |
| `configureDashboardBlockItems()` | `welcome` (size 5, no background) and `analytics_card` (size 3) | The built-in blocks, kept whether or not `configureWidgetItems()` calls its parent |
| `configureMenuBeforeItems()` | `[]` | The sidebar's first entries |
| `configureMenuItems()` | nothing when the dashboard has groups, else one entry per registered CRUD | The sidebar's own entries (the system pages, as a rule) |
| `configureMenuAfterItems()` | `[]` | The sidebar's last entries |
| `configureUserMenu()` | `MenuBuilder::buildUserMenuDefault()` | The account menu |
| `configureSidebarBlockItems()` | the `analytics_widget` block | The block under the sidebar's icons |

How the sidebar is assembled from these is in
[System pages](system-pages.md#the-sidebar-on-every-screen). A super-administrator
customises the dashboard in place (order, size, hidden, background); the layout is
kept per scope (`LayoutScope::DASHBOARD`, `SIDEBAR`, `CRUD`) by `LayoutStore` and
saved through `admin_layout_save`.

## `MenuItem`

`Base\Admin\Config\MenuItem`'s factories build `Base\Admin\Config\Menu\MenuItem`s:

| Factory | Item |
|---|---|
| `MenuItem::linkToCrud($entityFqcn, $label = null, $icon = null)` | The CRUD registered for that class, else for its nearest parent class |
| `MenuItem::linkToRoute($route, $parameters = [], $label = null, $icon = null)` | A route |
| `MenuItem::linkToUrl($label, $icon, $url)` | An address |
| `MenuItem::linkToDashboard($label, $icon = null)` | The dashboard |
| `MenuItem::linkToLogout($label, $icon = null)`, `linkToExitImpersonation($label, $icon = null)` | Sign out; leave an impersonation |
| `MenuItem::section($label = null, $icon = null)` | A group, its links given by `setSubItems([...])` |
| `MenuItem::subMenu($label, $icon = null, $url = null)` | A submenu |
| `MenuItem::block($blockName, $label = null, $icon = null, $instanceKey = null, $params = [])` | A widget of a widget type (below); `$instanceKey` keeps each instance's customisation apart |

An item takes `setCrudAction('new')` (a "create" shortcut), `setPermission($attribute)`
(shown only to whom it is granted), `setBadge()`, `setLinkTarget()`,
`setDescription()`, `setCssClass()`, `setSize()`, `setHeight()`, `setBackground()`,
`setHidden()`.

```php
public function configureWidgetItems(): iterable
{
    yield MenuItem::section('menu.section.blog', 'fa-solid fa-newspaper')->setSubItems([
        MenuItem::linkToCrud(Article::class, 'menu.item.articles', 'fa-solid fa-newspaper'),
        MenuItem::linkToCrud(Article::class, 'menu.item.articles', 'fa-solid fa-plus-circle')->setCrudAction('new'),
    ]);

    yield MenuItem::block('analytics_card', 'Last 30 days', 'fa-solid fa-chart-line', '30d', ['days' => 30]);
}
```

## Widget types

A block names a widget type by its name. The bundle registers:

| Name | Class | Parameters | What it shows |
|---|---|---|---|
| `welcome` | `WelcomeWidgetType` | | The greeting |
| `analytics_card` | `AnalyticsCardWidgetType` | `days` (14), `series` | The site's traffic, day by day (`Base\Service\Analytics`), with the timeline's events; `series` the lines plotted (all but bots and AI by default) |
| `entity_views` | `EntityViewsWidgetType` | `days` (14), `entityClass`, `entityId`, `series` | The page views of one record, or of every record of a class |
| `counter` | `CounterWidgetType` | `mode` (`views` or `records`), `days` (14), `entityClass`, `entityId`, `countClass`, `scopeId`, `scopeClass`, `states`, `series` | One number and its trend: the views of a record, or how many records there are (narrowed to a parent and to states) |
| `composite` | `CompositeWidgetType` | `panes`: a list of `{type, label, params}` | Two or more widgets of the registered types side by side in one card |
| `admin_complaints` | `ComplaintsWidgetType` | | The open reports |

All but `composite` and `admin_complaints` are in the palette a
super-administrator adds widgets from (`PaletteDashboardWidgetTypeInterface`).

## Writing a widget type

A service implementing `Base\Admin\Widget\DashboardWidgetTypeInterface` is a
widget type (autoconfigured, tag `base.admin.dashboard_widget_type`):

```php
namespace App\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Widget\DashboardWidgetTypeInterface;

final class OpenOrdersWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(private readonly OrderRepository $orders)
    {
    }

    public static function getName(): string
    {
        return 'open_orders';
    }

    public function getTemplate(): string
    {
        return 'admin/widget/open_orders.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        return ['count' => $this->orders->countOpen($widget->getParams()['days'] ?? 7)];
    }
}
```

and the dashboard yields `MenuItem::block('open_orders', 'Open orders', 'fa-solid fa-box')`.
Implementing `PaletteDashboardWidgetTypeInterface` instead (`getDefaultLabel()`,
`getDefaultIcon()`) also puts it in the palette (tag
`base.admin.dashboard_widget_type.palette`). In a template,
`dashboard_widget_type(name)` gives the type.

### The dashboard card

The admin's look is a light page (`--admin-bg`), white cards without a border, a large radius
(`--admin-card-radius`, 16px) and a soft shadow (`--admin-card-shadow`), one dark navy for text
(`--admin-text`, its greys navy greys: `--admin-muted`) and an indigo accent (`--admin-accent`),
light and dark. A section's card (its links) puts its title above the white card, its links as
tinted rows that alternate, each with its icon and a chevron.

A widget type's template takes the same card from these classes, without CSS of its own for
the chrome:

```twig
<section class="dash-card dash-card--compact">
    <header class="dash-card-head">
        <div class="dash-card-titles">
            <h2 class="dash-card-title">{{ widget.label|trans({}, 'admin') }}</h2>
            <span class="dash-card-sub">30 derniers jours</span>
        </div>
        <span class="dash-card-actions">
            <a class="dash-card-action" href="..." title="Ouvrir la liste"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <button type="button" class="item-settings dash-card-action" data-widget-settings-toggle><i class="fa-solid fa-ellipsis-vertical"></i></button>
        </span>
    </header>
    <div class="dash-card-panel">
        <ul class="dash-rows">
            <li><a class="dash-row" href="..."><i class="fa-solid fa-chart-line dash-row-icon"></i><span class="dash-row-label">Produits<small>exercice 2026</small></span><span class="dash-row-value">21 168,00 €</span></a></li>
            <li><div class="dash-row"><i class="fa-solid fa-paper-plane dash-row-icon"></i><span class="dash-row-label">Appeler les loyers</span><a class="dash-row-action" href="...">Appeler</a></div></li>
        </ul>
        <p class="dash-empty">Aucune statistique</p>
    </div>
</section>
```

`.dash-card-head` sits above the card: the title in bold navy, what it covers, the actions (the
⋮ settings show while customizing only). `.dash-card-panel` is the white card. `.dash-rows` are
tinted rows that alternate, the label bold on the left, an icon, the value right-aligned, or an
action as a small button (`.dash-row-action`) - no underlined link lists. `.dash-empty` is a
quiet centred empty state. On a phone (720px and less) every card takes the line, but a
`.dash-card--compact` (one figure and its context) sits two to a row on a small tablet (561-720px), alone on a phone. Labels are in the text
face at a readable size: no small capitals.

### A settings panel from markup

A widget type's template gets a settings panel in the layout editor with no
script of its own: a toggle `[data-widget-settings-toggle]` (give it the
`item-settings` class: it shows only while customizing), a panel
`[data-widget-settings-panel]` (with `hidden`, and the `widget-settings-panel`
class for the editor's style) whose fields name the parameter they set, and
the two buttons:

```twig
<button type="button" class="item-settings" data-widget-settings-toggle><i class="fa-solid fa-ellipsis-vertical"></i></button>
<div class="widget-settings-panel" data-widget-settings-panel hidden>
    <select data-widget-param="days"><option value="7">7 days</option><option value="30">30 days</option></select>
    <label><input type="checkbox" data-widget-param="sites" value="3"> Strasbourg</label>
    <label><input type="checkbox" data-widget-param="compact" data-widget-param-bool> Compact</label>
    <button type="button" data-widget-settings-cancel>Cancel</button>
    <button type="button" data-widget-settings-apply>Apply</button>
</div>
```

Opening it reads the block's live parameters back into the fields; applying
merges them into `data-widget-params` (a select, a radio or a text field its
value, a number field a number; checkboxes sharing a name a list, digits as
integers, an empty list removed so the widget's default applies; a checkbox
with `data-widget-param-bool` a boolean). "Terminé" saves them with the
layout and reloads the dashboard, which renders the widget with them.
omnibase/ledger's bank cards use it for their period and accounts.

The timeline the analytics widgets draw reads the services implementing the
core's `Base\Service\TimelineEventProviderInterface` (tag
`base.admin.timeline_event_provider`).
