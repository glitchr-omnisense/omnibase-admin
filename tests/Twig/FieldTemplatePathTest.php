<?php

namespace Tests\Base\Admin\Twig;

use Base\Field\FieldDescriptor;
use Base\Field\FieldValueResolver;
use Base\Field\TextField;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * A field that names its own template (setTemplatePath()) is printed by it on
 * the list and on the detail page: both included @Admin/<templateName> only,
 * so a bundle's template - omnibase/marketplace's attachments, a link per
 * file - was ignored and the cell read "2 items". The two pages include
 * @Admin/crud/_field.html.twig, rendered here as they render it.
 */
class FieldTemplatePathTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    private function cell(TextField $field, object $entity, string $page = FieldDescriptor::PAGE_DETAIL): string
    {
        $resolved = static::getContainer()->get(FieldValueResolver::class)->resolveAll([$field->getAsDto()], $entity, $page)[0];

        return trim(static::getContainer()->get('twig')->render('@Admin/crud/_field.html.twig', ['field' => $resolved, 'entity' => $entity]));
    }

    public function testTheFieldsOwnTemplatePrintsIt(): void
    {
        $record = new FieldTemplatePathRecord();
        $field = TextField::new('contact')->setTemplatePath('@Admin/crud/field/email.html.twig');

        foreach ([FieldDescriptor::PAGE_DETAIL, FieldDescriptor::PAGE_INDEX] as $page) {
            $this->assertStringContainsString('href="mailto:desk@example.org"', $this->cell($field, $record, $page), $page.': the template the field names');
        }
    }

    public function testWithoutOneTheAdminsTemplateForItsKindPrintsIt(): void
    {
        $html = $this->cell(TextField::new('contact'), new FieldTemplatePathRecord());

        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringContainsString('desk@example.org', $html, 'the text template');
    }

    public function testATemplateThatIsNotThereFallsBackToTheAdmins(): void
    {
        $html = $this->cell(TextField::new('contact')->setTemplatePath('@Admin/crud/field/none_such.html.twig'), new FieldTemplatePathRecord());

        $this->assertStringContainsString('desk@example.org', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function testSetTemplateNameStillWinsOverAnEarlierPath(): void
    {
        // FieldTrait::setTemplateName() forgets the path set before it: the admin's template is used.
        $field = TextField::new('contact')->setTemplatePath('@Admin/crud/field/email.html.twig')->setTemplateName('crud/field/text');

        $this->assertStringNotContainsString('mailto:', $this->cell($field, new FieldTemplatePathRecord()));
    }
}

final class FieldTemplatePathRecord
{
    public function getId(): int
    {
        return 1;
    }

    public function getContact(): string
    {
        return 'desk@example.org';
    }
}
