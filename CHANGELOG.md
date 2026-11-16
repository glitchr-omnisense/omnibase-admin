# Changelog

## 2.0.0

omnibase/admin is the back office of omnisense/omnibase: CRUD screens built from field descriptors, the dashboard and
its widgets, menus, system pages, and the screens of the core's own entities. It names only `Base\` classes and reads
and writes every record through the persistence the site chose (`Base\Database\Repository\Repositories`, the core's
finder language, `Base\Contract\UnitOfWork`): a list, its search, its filters (`Filter::matchWith()`), its pages and
the previous/next record are criteria of the finder language for every entity class, with or without a repository class
of its own; a screen narrows its list with `indexCriteria()` and writes through the `*Record()` hooks. Nothing of an
ORM is part of its API, on Doctrine and on Eloquent alike.
