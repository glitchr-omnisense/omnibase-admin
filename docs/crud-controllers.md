---
title: Writing a CRUD
order: 20
---

# Writing a CRUD

A CRUD is a controller extending `Base\Admin\Controller\AbstractCrudController`.
It implements `CrudControllerInterface`, which autoconfiguration tags
`base.admin.crud_controller` and `controller.service_arguments`: registered as a
service (the application's `src/` is, by Symfony's default configuration), it
gets its routes, its menu entry and its links from every other screen.

## A complete CRUD

The bundle's own CRUD of the core's redirections
(`Base\Admin\Controller\Crud\RedirectionCrudController`):

```php
namespace Base\Admin\Controller\Crud;

use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\Layout\Redirection;
use Base\Field\BooleanField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class RedirectionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Redirection::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-diamond-turn-right';
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort(['hits' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('source', '@admin.redirection.source')->setColumns(6)
            ->setHelp('@admin.redirection.source_help');
        yield TextField::new('target', '@admin.redirection.target')->setColumns(6)
            ->setHelp('@admin.redirection.target_help');
        yield IntegerField::new('status', '@admin.redirection.status')->setColumns(4)
            ->setFormType(ChoiceType::class)->setFormTypeOptions([
                'choices' => ['redirection.status_301' => Redirection::PERMANENT, 'redirection.status_302' => Redirection::TEMPORARY],
                'translation_domain' => 'admin',
                'choice_translation_domain' => 'admin',
            ]);
        yield BooleanField::new('enabled', '@admin.redirection.enabled')->setColumns(4);
        yield IntegerField::new('hits', '@admin.redirection.hits')->hideOnForm();
        yield DateTimeField::new('lastHitAt', '@admin.redirection.last_hit_at')->hideOnForm();
        yield DateTimeField::new('createdAt', '@admin.redirection.created_at')->onlyOnDetail();
    }
}
```

## The entity

`getEntityFqcn()` is guessed from the controller's name when it is not
overridden: `CrudController` is taken off, and each of
`\Controller\Crud\`, `\Controller\Admin\Crud\`, `\Admin\Controller\Crud\`,
`\Controller\Backoffice\Crud\` is replaced by `\Entity\` until a class exists
(`App\Controller\Crud\Blog\ArticleCrudController` manages `App\Entity\Blog\Article`).
None found: a `LogicException` asks to override it. The reverse,
`AbstractCrudController::getCrudControllerFqcn($entityOrClass)`, finds the CRUD of
an entity - the registered one first, an `App\` class before a `Base\` one, then
the CRUD of the nearest parent class.

`getPreferredIcon()` gives the CRUD's icon in the menu and the dashboard.

## `configureCrud(Crud $crud)`

The default sorts by `id` descending, 20 rows a page. `Base\Admin\Config\Crud`:

| Method | Effect |
|---|---|
| `setEntityLabelInSingular($label)`, `setEntityLabelInPlural($label)` | The entity's name on the pages (otherwise `admin_entity_name()`: its translation in `entities`, else its class name in words) |
| `setPageTitle($pageName, $title)`, `setHelp($pageName, $help)` | A page's title, its help text (`Crud::PAGE_INDEX`, `PAGE_DETAIL`, `PAGE_EDIT`, `PAGE_NEW`) |
| `setDefaultSort(['field' => 'ASC'\|'DESC', ...])` | The list's order |
| `setPaginatorPageSize(int)` | Rows per page |
| `setSearchFields([...])` | The properties the search box looks in; empty: every `string` or `text` column of the list's fields (`LOWER(...) LIKE %query%`) |
| `showEntityActionsInlined()`, `showEntityActionsAsDropdown()` | A row's actions as buttons or in a menu |
| `setFormOptions($new, $edit = null)` | The options of the new and edit forms |
| `setEntityPermission($permission)` | The attribute every page of the CRUD requires ([Who may write in a CRUD](crud-access.md)) |

Labels are translation keys or `TranslatableInterface`s ([CRUD fields](crud-fields.md#the-words-of-a-crud-page-are-translated-once)).

## `configureFields(string $pageName)`

Yields `Base\Field\*Field` descriptors (omnisense/omnibase's): `IdField`,
`TextField`, `TextareaField`, `IntegerField`, `NumberField`, `MoneyField`,
`BooleanField`, `DateField`, `DateTimeField`, `SelectField`, `AssociationField`,
`CollectionField`, `ImageField`, `FileField`, `WysiwygField`, `EditorField`,
`SlugField`... (the classes of `vendor/omnisense/omnibase/src/Field`). `$pageName`
is the page being built; `onlyOnIndex()`, `onlyOnDetail()`, `hideOnForm()`... say
where a field appears, `setColumns()` its width, `setFormType()` and
`setFormTypeOptions()` its form field. `Base\Admin\Form\FieldFormBuilder` makes
each one the form field of its type. The rules for related records, enums,
collections and required fields are in [CRUD fields](crud-fields.md).

## `configureActions(Actions $actions)`

The default (`Actions::addDefaults()`):

| Page | Actions |
|---|---|
| `index` | `new`, `edit`, `delete`, `detail` |
| `detail` | `edit`, `delete`, `index` |
| `edit` | `saveAndReturn`, `saveAndContinue`, `index` |
| `new` | `saveAndReturn`, `saveAndAddAnother`, `saveAndContinue`, `index` |

and, for an entity with an address on the site, a link to it. `Base\Admin\Config\Actions`:

| Method | Effect |
|---|---|
| `add($page, Action\|string $action, $icon = null, $callable = null)` | Adds an action to a page |
| `update($page, $name, callable $fn)`, `remove($page, $name)` | Changes or removes one |
| `disable(...$names)` | Removes them from every page; their routes answer 404 |
| `setPermission($name, $permission)`, `setPermissions([...])` | The attribute an action requires |
| `openTo($role, ...$custom)`, `allowAnyoneTo(...$names)` | Opens the writing actions to a role; lets any administrator run some ([Who may write in a CRUD](crud-access.md)) |

`Base\Admin\Config\Action::new($name, $label = null, $icon = null)` builds one:
`createAsGlobalAction()` (on the page, not on a row), `createAsBatchAction()`,
`linkToCrudAction($method)`, `linkToRoute($route, $parameters)`,
`linkToUrl($url)`, `askConfirmation($question)`, `displayIf(callable)`,
`setPermission()`, `renderAsButton()`, `renderAsLink()`, `targetBlank()`,
`setCssClass()`, `setHtmlAttributes()`. Its constants name the built-in ones
(`Action::NEW`, `EDIT`, `DELETE`, `DETAIL`, `INDEX`, `BATCH_DELETE`,
`SAVE_AND_RETURN`, `SAVE_AND_CONTINUE`, `SAVE_AND_ADD_ANOTHER`).

## A CRUD's own actions: `#[AdminAction]`

A public method with `Base\Admin\Attribute\AdminAction` is one more route of the
CRUD, named like the built-in ones (`admin_crud_<slug>_<method>`), and
`AdminActionSubscriber` runs the CRUD's gate before it: the entity permission,
the action's permission, a disabled action's 404 and, for a method that writes,
the CSRF token.

```php
use Base\Admin\Attribute\AdminAction;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Symfony\Component\HttpFoundation\Response;

public function configureActions(Actions $actions): Actions
{
    return parent::configureActions($actions)
        ->add(Actions::PAGE_INDEX, Action::new('refreshRates', 'Refresh the rates', 'fa-solid fa-rotate')
            ->createAsGlobalAction()
            ->linkToCrudAction('refreshRates')
            ->askConfirmation('Fetch today\'s rates now?'));
}

#[AdminAction('/refresh')]
public function refreshRates(): Response
{
    // gate and CSRF token already checked
    $this->rates->refresh();
    $this->addFlash('success', 'Rates refreshed.');

    return $this->redirectToIndex();
}
```

| Argument | Default | Effect |
|---|---|---|
| `path` | `null`: `/<kebab-cased method>` | Relative to the CRUD's address; a path holding `{entityId}` makes it a record's action (the method takes `string $entityId` and loads it with `$this->findEntity($entityId)`) |
| `methods` | `['POST']` | The HTTP methods of the route; one that takes no GET is drawn as a small form (its token, a button, the confirmation) instead of a link |
| `csrf` | `true` | Check the token (`admin-action-<method>`, posted as `_token` or an `X-CSRF-Token` header) before a writing method runs |
| `requirements` | `[]` | More route requirements |

The marketplace's `exchange_rates/refreshRates`, the invoices' `send`, `markPaid`,
`credit` and the complaints' `handle`, `dismiss`, `reopen` are such actions.

## `configureFilters(Filters $filters)`

`Filters::add('property')` adds a filter whose kind is guessed from the mapping
(boolean, numeric for `integer`, `smallint`, `bigint`, `float`, `decimal`, date for
`date`, `datetime`, `datetime_immutable`, `date_immutable`, text otherwise);
`Filters::add(Filter::new('property', 'Label')->asChoice([...]))` gives one
explicitly: `asBoolean()`, `asDate()` (a `from`/`to` range), `asNumeric()` (`=`,
`>`, `<`, `≠`), `asChoice($choices)`, `asAssociation($entities, $labelCallback)`,
or `applyWith(callable)` for a query of its own. A CRUD of a `Thread` gets a
`state` filter unless it declares one.

A property added by its name alone is guessed from its column: a boolean, a number, or a date
range for every moment (`date`, `datetime`, `datetimetz` and the core's `utc_datetime`, each
mutable or immutable). The default `state` filter is labelled `State` (the `forms` domain) and
offers the states by their value, with the core's enum words (`published`, `draft`...) in the
reader's language.

## Hooks

| Method | Default | Override to |
|---|---|---|
| `configureRecordNote(object $entity): ?string` | `null` | Show a line about the record under its heading on the detail and edit pages (plain text, escaped) |
| `isDeletable(object $entity): bool` | `true` | Refuse deleting a record others rely on: the button disappears and the delete routes refuse it |
| `createEntity(string $fqcn): object` | the constructor, its owner parameter (`owner`, `author` typed with the user's class) filled with the signed-in user | Build a new record otherwise |
| `persistEntity($em, $entity)`, `updateEntity($em, $entity)` | `persist()` and `flush()`; `flush()` | Do more on a save |
| `removeEntity($em, $entity)` | `remove()` | Change how a record goes (single and batch deletes both run it); `deleteEntity()` adds the flush |
| `createIndexQueryBuilder(Crud $crud, array $sort)` | the repository's query builder, alias `entity`, sorted | Narrow the list |

The pages (`index`, `detail`, `new`, `edit`, `delete`, `batchDelete`, `toggle`,
`collectionEntries`) are public methods too; an override calls the parent or
renders with `renderCrud()`. `findEntity($entityId)` finds a record by its id or
its address identifier, `redirectToIndex()` goes back to the list.

## Routes and addresses

Every registered CRUD gets, under `/admin/<slug>`:

| Route | Path | Methods |
|---|---|---|
| `admin_crud_<slug>_index` | `/admin/<slug>` | GET |
| `admin_crud_<slug>_new` | `/admin/<slug>/new` | GET, POST |
| `admin_crud_<slug>_batchDelete` | `/admin/<slug>/batch-delete` | POST |
| `admin_crud_<slug>_detail` | `/admin/<slug>/{entityId}` | GET |
| `admin_crud_<slug>_edit` | `/admin/<slug>/{entityId}/edit` | GET, POST |
| `admin_crud_<slug>_delete` | `/admin/<slug>/{entityId}/delete` | POST |
| `admin_crud_<slug>_toggle` | `/admin/<slug>/{entityId}/toggle` | PATCH |
| `admin_crud_<slug>_collectionEntries` | `/admin/<slug>/{entityId}/collection/{field}` | GET |
| `admin_crud_<slug>_<method>` | the `#[AdminAction]`'s path | its methods |

The slug is the controller's namespace after `Crud\` (or its short name), each
segment kebab-cased and pluralised, a repeated segment collapsed, joined with `/`
(`Base\Routing\AdminRouteNames::slugify()`, the core's): `Crud\UserCrudController`
is `users`, `Crud\User\GroupCrudController` `users/groups`; in a route name `/` and
`-` become `_` (`admin_crud_users_groups_index`).

In PHP the address is built by `Base\Admin\Router\AdminUrlGenerator`, in Twig by
`admin_url()`:

```php
$url = $this->adminUrlGenerator
    ->setController(UserCrudController::class)
    ->setAction('edit')
    ->setEntityId($user->getId())
    ->generateUrl();
```

```twig
<a href="{{ admin_url('Base\\Admin\\Controller\\Crud\\UserCrudController', 'edit', user.id) }}">...</a>
```

`{entityId}` is the record's readable identifier when it stores one (`admin.url_identifier`,
[CRUD fields](crud-fields.md#a-records-address)); a GET by another identifier is
redirected to the canonical one.

## The CRUDs the bundle ships

| Controller | Entity (omnisense/omnibase) | Slug |
|---|---|---|
| `Crud\UserCrudController` | `Base\Entity\User` | `users` |
| `Crud\User\ConnectionCrudController` | `Base\Entity\User\Connection` | `users/connections` |
| `Crud\User\GroupCrudController` | `Base\Entity\User\Group` | `users/groups` |
| `Crud\User\PermissionCrudController` | `Base\Entity\User\Permission` | `users/permissions` |
| `Crud\ComplaintCrudController` | `Base\Entity\User\Complaint` (with `handle`, `dismiss`, `reopen`) | `complaints` |
| `Crud\TextOverrideCrudController` | `Base\Entity\Layout\TextOverride` | `text-overrides` |
| `Crud\RedirectionCrudController` | `Base\Entity\Layout\Redirection` | `redirections` |
| `Crud\Thread\TagCrudController`, `Crud\Thread\TaxonCrudController` | `Base\Entity\Thread\Tag`, `Taxon` | `threads/tags`, `threads/taxons` |
| `Crud\Layout\SettingCrudController`, `Crud\Layout\ImageCrudController` | `Base\Entity\Layout\Setting`, `Image` | `layouts/settings`, `layouts/images` |
| `Crud\Layout\Attribute\AdapterCrudController` | `Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter` | `layouts/attributes/adapters` |
| `Crud\Layout\Widget\PageCrudController` | `Base\Entity\Layout\Widget\Page` | `layouts/widgets/pages` |

All under `Base\Admin\Controller\`; each is overridden by the application's class
of the same name under `App\Admin\Controller\`. Their former names
(`Base\Controller\Backoffice\Crud\*`) are kept as aliases for one release
(`src/Resources/former-names.php`).
