<?php

namespace Tests\Base\Admin\Translation;

use Base\Admin\Filter\Filters;
use Base\Field\FieldDescriptor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * A field that names no label shows its property humanised ("createdAt" ->
 * "Created at"), translated through the `forms` domain by the form, the
 * list's columns and its filters. "State" and "Slug" were in no language, so
 * every StateField and SlugField of every bundle read English: the fields
 * and filters of omnibase's own screens, and the names the bundles' screens
 * share, are in each language the domain carries.
 */
class FieldLabelsCatalogueTest extends TestCase
{
    /** The screens of the core's own entities (in the core until omnibase 4.0, Base\Controller\Backoffice\Crud). */
    private const CORE_SCREENS = [
        \Base\Admin\Controller\Crud\UserCrudController::class,
        \Base\Admin\Controller\Crud\User\ConnectionCrudController::class,
        \Base\Admin\Controller\Crud\User\GroupCrudController::class,
        \Base\Admin\Controller\Crud\User\PermissionCrudController::class,
        \Base\Admin\Controller\Crud\Thread\TagCrudController::class,
        \Base\Admin\Controller\Crud\Thread\TaxonCrudController::class,
        \Base\Admin\Controller\Crud\Layout\ImageCrudController::class,
        \Base\Admin\Controller\Crud\Layout\SettingCrudController::class,
        \Base\Admin\Controller\Crud\Layout\Attribute\AdapterCrudController::class,
        \Base\Admin\Controller\Crud\Layout\Widget\PageCrudController::class,
    ];

    /** Names every bundle's screens share, whatever omnibase's own use. */
    private const SHARED = ['Slug', 'State', 'Status', 'Created at', 'Updated at', 'Published at', 'Title', 'Parent', 'Featured', 'Visible'];

    /** Symfony's FormRenderer::humanize(), which gives a field without a label its text. */
    private static function humanize(string $text): string
    {
        return ucfirst(strtolower(trim(preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $text))));
    }

    /** @return array<string, array<string, mixed>> language => the domain's catalogue */
    private static function catalogues(): array
    {
        $catalogues = [];
        // The core's `forms` domain, which the back office's form, columns and filters translate through.
        $core = \dirname((string) (new \ReflectionClass(\Base\BaseBundle::class))->getFileName(), 2);
        foreach (glob($core.'/translations/forms+intl-icu.*.yaml') ?: [] as $file) {
            $catalogues[explode('.', basename($file))[1]] = Yaml::parseFile($file) ?? [];
        }

        return $catalogues;
    }

    /** @return list<string> the humanised texts omnibase's own screens show, from their fields and filters */
    private function screenTexts(): array
    {
        $texts = [];
        foreach (self::CORE_SCREENS as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }
            // configureFields() and configureFilters() need none of the controller's services.
            $crud = $reflection->newInstanceWithoutConstructor();
            foreach ([FieldDescriptor::PAGE_INDEX, FieldDescriptor::PAGE_NEW, FieldDescriptor::PAGE_EDIT, FieldDescriptor::PAGE_DETAIL] as $page) {
                foreach ($crud->configureFields($page) as $field) {
                    $descriptor = $field->getAsDto();
                    if (null === $descriptor->getLabel() && null !== $descriptor->getProperty()) {
                        $texts[] = self::humanize($descriptor->getProperty());
                    }
                }
            }
            foreach ($crud->configureFilters(Filters::new())->getAll() as $filter) {
                if (null === $filter->getLabel()) {
                    $texts[] = self::humanize($filter->getProperty());
                }
            }
        }

        return array_values(array_unique($texts));
    }

    public function testEveryLabelOmnibaseLeavesToItsPropertyIsInEveryLanguage(): void
    {
        if (!class_exists(Filters::class)) {
            self::markTestSkipped('Requires omnibase/admin (the omnibase harness).');
        }

        $texts = $this->screenTexts();
        self::assertContains('Slug', $texts, 'the screens are read');

        $missing = [];
        foreach (self::catalogues() as $language => $catalogue) {
            foreach (array_unique([...$texts, ...self::SHARED]) as $text) {
                if (!\is_string($catalogue[$text] ?? null)) {
                    $missing[] = $language.': "'.$text.'"';
                }
            }
        }
        self::assertSame([], $missing, 'shown in English whatever the language');
    }
}
