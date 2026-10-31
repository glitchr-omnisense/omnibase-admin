# Changelog

## [Unreleased]

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
