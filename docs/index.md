# omnibase/admin

The back office of the omnibase sites: CRUD controllers generated from an
entity and its fields, a dashboard of widgets, a sidebar, the system pages
(settings, API keys, opening hours, the trash, reports, site texts,
redirections), with no dependency on EasyAdmin. It owns the `Base\Admin\`
namespace and runs on [omnisense/omnibase](https://github.com/glitchr-omnisense/omnibase)
3 or 4, under Symfony (through omnibridge/symfony).

Three ideas shape it:

- **One field system.** A CRUD yields `Base\Field\*Field` descriptors (omnisense/omnibase),
  each wired to a real Symfony form type (`Base\Field\Type\*Type`): the same
  fields work in the back office and in any form of the site.
- **A plain CRUD pipeline.** `AbstractCrudController`'s actions (`index`,
  `detail`, `new`, `edit`, `delete`, `batchDelete`, `toggle`) are ordinary
  controller methods a CRUD overrides; the routes are generated for every
  registered CRUD.
- **A website in the website.** The back office opens as a transparentJS overlay
  over the page the visitor was on (`X-Transparent-Nest`); without
  transparentJS every link is an ordinary page load.

Use it for a site's administration. A site that only needs a page or two of its
own under `/admin` can route them there itself: they are nested and get the
sidebar ([System pages](system-pages.md)).

## How this manual is organised

- [Installation](installation.md): requirements, the bundle, the routes, the
  dashboard controller.
- [Writing a CRUD](crud-controllers.md): the controller, its configuration
  (`Crud`, `Actions`, `Action`, `Filters`), its hooks, `#[AdminAction]`, its
  routes and addresses, the CRUDs the bundle ships.
- [The dashboard and the menu](dashboard.md): the dashboard's hooks, `MenuItem`,
  the widget types, writing a widget type.
- [CRUD fields](crud-fields.md), [Who may write in a CRUD](crud-access.md),
  [System pages, nest, reports, site texts](system-pages.md),
  [Graphs in the back office](graphs.md): the existing guides.
- [Configuration reference](configuration.md): `admin:`, the tags, the services
  it replaces.
- [Reference](reference.md): the routes, the Twig functions and globals, the
  listeners, the voter.
