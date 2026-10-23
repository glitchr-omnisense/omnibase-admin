<?php

namespace Base\Admin\Controller\Crud\Thread;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\IconField;
use Base\Field\IdField;
use Base\Field\NumberField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Admin\Filter\Filters;
use Base\Entity\Thread\Tag;

class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-tag';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('slug')->add('priority');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        // Made from the label as it is typed (Tag::$slug's #[Slugify(reference: "translations.label")]).
        yield SlugField::new('slug')->setTargetFieldName('translations.label')->setColumns(3);
        yield IconField::new('icon')->setRequired(false)->setColumns(3)->hideOnIndex();
        yield NumberField::new('priority')->setColumns(3);
        yield TranslationField::new()->hideOnIndex()->setFields(['label' => []]);
    }
}
