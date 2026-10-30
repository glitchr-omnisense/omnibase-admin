# Changelog

## [Unreleased]

### Added
- A widget type's settings panel from markup alone: `[data-widget-settings-toggle]`,
  `[data-widget-settings-panel]` with fields `[data-widget-param="name"]`,
  `[data-widget-settings-apply]` and `[data-widget-settings-cancel]`, handled once in
  `layout.html.twig` (the live parameters read back on opening, the fields merged into
  `data-widget-params` on applying, saved with the layout). A bundle's widget type no longer needs
  its own script in the layout for its settings (docs/dashboard.md).

### Fixed
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
