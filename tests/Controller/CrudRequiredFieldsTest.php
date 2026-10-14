<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Test\CrudFieldsTestCase;
use Symfony\Component\Translation\Translator;

/**
 * The fields marked required on the back office's own screens, against what
 * their records need (see CrudFieldsTestCase): only those the record cannot
 * do without, all of them, an optional one able to take an empty value, one
 * field per property, a slug following its title.
 */
final class CrudRequiredFieldsTest extends CrudFieldsTestCase
{
    protected static function sourceDirectory(): string
    {
        return __DIR__.'/../../src';
    }

    protected function services(): array
    {
        // The wordings' screen offers the keys of the site's catalogue, in its first language.
        return ['translator' => new Translator('fr'), 'locales' => ['fr', 'en']];
    }
}
