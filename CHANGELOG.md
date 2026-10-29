# Changelog

## [Unreleased]

### Fixed
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
