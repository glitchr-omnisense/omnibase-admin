# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

### Changed

- Installs with the default minimum-stability: stable requirements (`omnisense/omnibase` ^5.1, `omnibridge/symfony` ^1.1), a branch alias,
  no `minimum-stability: dev` left to the sites.
- CI: glitchr-studio/ci's package workflow (the bundle installed alone, composer validate, PHPUnit,
  PHPStan against its baseline, a consumer install without require-dev) and its release workflow.
- `doctrine/orm` in require-dev: the write hooks' tests run without a host.

### Fixed
- A field's own template (`setTemplatePath()`) prints it on the list and on the detail page: both included
  `@Admin/<templateName>.html.twig` only, so a bundle's template was ignored (omnibase/marketplace's attachments on an
  order or a quote read "2 items" in place of a link per file). The two pages include `@Admin/crud/_field.html.twig`:
  the field's template, else the admin's for its kind, else the text one. `FieldTemplatePathTest`.

### Changed
- The page's trail no longer reads as an error of the form: its section was printed as a bare `<li><span>...</span></li>`,
  the shape of a form's error list, so a page read for its errors (omnibase/marketplace's "without a script" tests) found
  a form error "The shop" beside the field's own. Its items carry classes now (`.admin-breadcrumb`,
  `.admin-breadcrumb-section`, `.admin-breadcrumb-link`); a field's error is said once, at the field.
  `FormErrorsOnceTest`.
- A new look for the back office: calm, organised, one accent. A soft grey page with white cards (hairline border,
  10px radius, a shadow that only separates), a fixed sidebar of sections (quiet section labels, 34px rows, the current
  page on the accent's soft fill), a slim white top bar with the search; the page's trail (section, then the list a
  record belongs to), title and actions; tables with hairline rows, a grey head, tabular figures and soft status
  badges (an enum's colour as `--badge-tone`, mixed so the text stays readable in both themes); the active filters as
  chips above the list, each dropping itself; empty lists that say why and offer to create or to clear; a record's
  fields as label/value rows under a "Details" head; a form's actions as a save bar at the foot of the window
  (`.global-actions.is-form-actions`, the committing action at its end, "Back to list" at its start); one primary
  button per row, filled with the accent, the others white keys; toasts, dialogs and menus on one radius and shadow
  scale; the sign-in page on the same tokens. Design tokens on `:root`, all overridable: a neutral scale mixed from the
  ink (`--admin-gray-50`...`-700`, `--admin-border-strong`, `--admin-hover`), the states' fills (`--admin-*-soft`),
  `--admin-space-1`...`-16` (a 4px grid), `--admin-radius-sm/-lg/-pill`, `--admin-shadow-md/-lg`, `--admin-topbar-h`,
  `--admin-font-mono`, `--admin-duration`/`--admin-ease`; every former token keeps its name and meaning
  (`--admin-radius` 6px, `--admin-card-radius` 10px, `--admin-aside-w` 256px now). The face is Source Sans 3 (Adobe,
  SIL Open Font License 1.1, the unmodified variable fonts in `public/fonts/` with their licence), the system stack until
  the file is published. Keyboard: `/` or Ctrl/Cmd+K reaches the search from anywhere, Up/Down/Enter pick a section,
  Escape leaves (and keeps a nest overlay open); a 2px focus ring everywhere, a halo on the fields; nothing moves under
  `prefers-reduced-motion`; real edges under forced colours. `Palette` derives a neutral grey page from the secondary's
  hue (no longer tinted by the primary) and measures contrast on the written `#rrggbb`.
- The search is on every page: on a CRUD's pages it still searches its list (`?query=`), and everywhere it lists the
  back office's sections matching the words, built from the sidebar (a site's or a bundle's menu items included, the
  hidden ones left out). New catalogue keys `topbar.search.*`, `datagrid.empty_filtered*`, `datagrid.clear`,
  `filters.clear`, `filters.remove`, `filters.yes/no`, `breadcrumb.label`, `detail.heading` (en, fr).
- The page's action row stays in the page's head: it is no longer hoisted into the top bar (it was, for superadmins,
  on a wide screen).
- A record's detail translates its labels as the list's column heads do (a bundle's `@domain.key` label showed raw).
- Doctrine only where omnibridge/doctrine is installed (omnibase's plan "omnibase agnostic", step B1d):
  `AbstractCrudController`'s entity manager is optional (null without Doctrine; what is still written on it - a list on
  Doctrine's QueryBuilder, a former write hook - is refused aloud there, the rest runs on the persistence chosen);
  `LinkableEntityRegistry` takes the `UnitOfWork` and lists the mapped classes from its entities' shapes (`classes()`,
  omnisense/omnibase 5.x and omnibridge/doctrine), Doctrine's metadata only under an older Doctrine bridge; no service of
  the bundle names a Doctrine service any more but the resolver's optional `doctrine`. composer.json suggests
  omnibridge/doctrine and omnibridge/eloquent. `WriteHooksTest::testWithoutDoctrineTheNewHooksWriteAndAFormerOneIsRefusedAloud`.
- The lists are read on the persistence the site chose (omnibase's plan "omnibase agnostic", step B1b): the search, an
  OR of the text fields containing the words whatever their case, and each active filter as groups of the core's finder
  language (`AbstractCrudController::indexCriteria()`, `searchCriterion()`, `Filter::match()`; `Group::of()`,
  omnisense/omnibase 5.x), their order and their page read through the repository's `findBy()` and `count()`
  (`Base\Admin\Index\RecordPage`, in place of Doctrine's Paginator), which omnibridge/doctrine and omnibridge/eloquent
  compile; the neighbouring record (previous/next) through `findOneBy()` on the id. A filter of its own says its condition
  with `Filter::matchWith()` (a criterion or a group of the finder). The former way stays, deprecated: a list is still built
  on Doctrine's QueryBuilder (`createIndexQueryBuilder()`, `applySearch()`, `applyFilters()`, `Filter::apply()`,
  `Base\Admin\Orm\Paginator`) under omnisense/omnibase 4.x or a core without `Group::of()`, on a repository that reads
  no finder (a plain Doctrine EntityRepository), and - with a deprecation - for a controller overriding one of those
  three or whose active filter has only `applyWith()` (omnibase/marketplace's and omnibase/newsletter's, until they move
  to `matchWith()`). On lesringostars the 386 lists of its 27 CRUDs read the same rows and totals both ways and on
  Eloquent (omnibridge/eloquent's AdminIndexParityTest). `IndexQueryTest`.
- The entities' mapping is read from the persistence chosen (omnibase's plan "omnibase agnostic", step B1c): the core's
  `EntityShape` (`UnitOfWorks::shapes()`, omnisense/omnibridge's Schema description) in place of Doctrine's
  ClassMetadata - the filters' guessed types, the search's text fields, the record lookup's subclasses and fields, the
  "new" menu's kinds (`AbstractCrudController::shape()`). `FieldFormBuilder` takes the `UnitOfWork` (an association's
  target and kind from its shape); Doctrine's ManagerRegistry is still accepted, deprecated. `CounterWidgetType` counts
  through the repository's `count()` (several states an IN) on the unit of work; `LinkableEntityRegistry` reads the
  instances through the `Repositories` and still lists the mapped classes from Doctrine's metadata (the core's
  EntityShapes has no listing yet).
- The records are found, written and removed through the persistence the site chose (omnibase's plan "omnibase
  agnostic", step B1a): `AbstractCrudController` holds omnisense/omnibridge's `UnitOfWork` and the core's
  `Repositories` (`setPersistence()`, beside the entity manager it keeps for the list's query and the metadata), finds a
  record by its id or its slug through `Repositories::of()`, and new, edit, the inline switch, delete and the batch
  delete write through the new hooks `persistRecord()`, `updateRecord()`, `deleteRecord()` and `removeRecord()`, typed
  on the `UnitOfWork`. The former hooks (`persistEntity()`...) stay, typed on Doctrine's entity manager and deprecated:
  a controller that overrides one is still given it, with the entity manager (a class defining both is given the new
  one) - unchanged on Doctrine; move the override to the new hook to run on Eloquent. Their parameter is not widened:
  PHP would refuse every override written against it (seven bundles have some). The trash, the revisions, the opening
  hours and the notifications' links on the `UnitOfWork` and the repositories too; ComplaintCrudController flushes
  the unit of work. On omnibridge/eloquent, the back office no longer writes Eloquent's entities through Doctrine's
  flush, nor reads a record through Doctrine's second-level cache that Eloquent's writes do not evict.
- The system pages type on the core's repository interfaces, not on the repositories' classes (omnibase's plan "omnibase
  agnostic", step E2'): SystemController on `Base\Repository\Layout\Settings`, HoursController on `Hours\WeekDays`,
  `Hours\ScopedWeeks` and `Hours\SpecialDays`, which omnibase 4.x and 5.0 both declare, with the methods they call. The
  complaints' dashboard tile keeps `ComplaintRepository`: `Base\Repository\User\Complaints` is 5.0's only, and the bundle
  serves the 4.x sites as well.
- Accepts omnisense/omnibase 5.0 (`3.0.*-dev || 4.0.*-dev || 5.0.*-dev`; plan "omnibase agnostic", step F6): the
  bundle runs unchanged on it, `Base\Bundle\AbstractBaseBundle` and `Base\BaseBundle` being aliases of
  omnibridge/symfony's classes there.

### Added
- The back office's colours, tunable per site: a primary (the accent) and a secondary (the ink),
  set on the settings page (`admin.theme.primary` / `.secondary`, `ThemeSettingsSection`, a colour
  picker beside each field and a live preview of the whole page through `admin_settings_theme`) or
  in the configuration (`admin.theme`), indigo and navy by default. Every colour token derives from
  them, light and dark (`Base\Admin\Theme\Palette`, `AdminTheme`, Twig `admin_theme_css()` in
  `<style id="admin-theme">`), kept at WCAG AA - a colour that falls short is darkened (light) or
  lightened (dark) and the settings page says so. New tokens `--admin-on-accent`, `--admin-focus`,
  `--admin-accent-soft`, `--admin-series-1`, `--admin-series-2`; the text on the accent takes
  `--admin-on-accent` (it was white, unreadable on the dark theme's light accent).
  docs/system-pages.md, docs/configuration.md.
- The dashboard card (`.dash-card`, `.dash-card-head` with its title above the card, its actions and
  the ⋮ settings, `.dash-card-panel`, `.dash-rows` of tinted rows with an icon, a value or an action
  button, `.dash-empty`, `.dash-card--compact` two to a row on a phone), which a bundle's widget
  takes without CSS of its own for the chrome (docs/dashboard.md).

### Changed
- Type: a 16px root and 15px body text (14 and 13.5 before, too small on every page), a scale for
  labels, card and page titles and figures (`--admin-fs-*`), more room in the table cells and the
  menu, the system font stack; no small-capital labels any more (the menu's sections, the dropdown
  headings, a phone's table labels). On a phone the compact dashboard cards take the whole width.
- The admin's look: a light, faintly blue page, white cards without a border, a 16px radius and a
  soft shadow; one dark navy for text (navy greys, no grey on grey) and an indigo accent; light and
  dark (`--admin-tint`, `--admin-tint-strong`, `--admin-card-radius`, `--admin-card-shadow`). A
  section's card puts its title above the card and its links as tinted rows with their icon and a
  chevron. The analytics card's figures and the settings panels' field labels are in the text face,
  not small capitals. On a phone the dashboard's gap is tighter.
- A widget type's settings panel from markup alone: `[data-widget-settings-toggle]`,
  `[data-widget-settings-panel]` with fields `[data-widget-param="name"]`,
  `[data-widget-settings-apply]` and `[data-widget-settings-cancel]`, handled once in
  `layout.html.twig` (the live parameters read back on opening, the fields merged into
  `data-widget-params` on applying, saved with the layout). A bundle's widget type no longer needs
  its own script in the layout for its settings (docs/dashboard.md).

### Fixed
- The sidebar's brand row: the logo is a link to the dashboard (« Tableau de bord »), the title and caret
  the account dropdown's button, two controls side by side (the caret stays in the collapsed rail);
  the logo and the account's avatar are served at 2x and 4x their box (`srcset`), sharp on a 2x
  screen. The dropdown's account row lines up with its items (same inset and gap, a 2.25rem
  avatar, the label and name beside it), and in the collapsed rail the dropdown is no longer
  clipped by the aside nor its items centred.
- A dashboard card shrinks with its block (`min-width: 0`): the analytics card stayed at the width
  it was drawn at (1188px in a window narrowed to 500px), Chart.js having written its canvas's width.
- A `DateField` reads as a day (`crud/field/date`, 05/12/2026), no longer `2026-12-05 00:00`.
- A text cell shows the field's own `formatValue()` over the enum's generic label (a rent call's
  state read "Called" where the screen labels it « Appelé »).
- Dashboard lines fill their width: `flex-grow` follows the widget's span, where the 240px column
  floor left 84px empty at the right of every line.
- A datagrid wider than its card keeps its horizontal scrollbar drawn (macOS hides overlay
  scrollbars), so the columns under the pinned actions read as more to scroll to, not as cut off.
- `crud/field/money`: an amount stored in cents shows in euros again - 1 480 € showed 14,80 €.
  omnibase's `format_currency` takes minor units (its `scale`, 100 by default) and the template
  divided by 100 first; cents now go in as they are, an amount in units with `{scale: 1}`.
- On a phone (720px and less) every dashboard card takes the line: the grid's stored column count,
  set inline, outweighed the one-column rule, so a span-1 card kept its desktop width (the 240px
  floor) beside empty space and a span-2 card overflowed the page.
- `Filters::add('createdAt')` on a moment gives a date range for every date type, the core's
  `utc_datetime` and `utc_datetime_immutable` included (and `datetimetz`); the guess knew
  Doctrine's own date types only and gave such a property a text filter.
- A Thread's default `state` filter is labelled `State` (translated by the `forms` domain) and
  offers the states by their value, labelled with the core's enum words in the reader's
  language. It was keyed by French literals (`'Publié' => STATE_PUBLISH`), so the label was the
  value the list was filtered on.
- `AdminRouteLoader`'s docblock gives the routes as they are: `{prefix}/{slug}/batch-delete`,
  `admin_crud_{slug}_batchDelete`, and the `collectionEntries` route.
- README: omnisense/omnibase on GitHub (it linked the abandoned gitlab.glitchr.dev), the
  requirement as composer.json states it (omnisense/omnibase 3.0 or 4.0), transparentJS's home
  (now `@glitchr/flowjs`).
