<?php

namespace Tests\Base\Admin\Form;

use Base\Admin\Form\FieldFormBuilder;
use Base\Entity\Thread\Comment;
use Base\Field\EditorField;
use Base\Field\TextField;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The fields of a CRUD form in its twelve-column grid (crud/_form_child.html.twig):
 * a row spanning the field's columns, the whole width without; a hidden field's
 * bare input. The editor is a hidden input its script fills (EditorType's parent
 * is HiddenType): it was laid bare in the grid as well, one column of twelve
 * wide and without its label - the text of a classroom card in a narrow strip.
 */
class FormGridTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/somewhere');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    private function grid(bool $csrf = false): \DOMXPath
    {
        $builder = (new FieldFormBuilder(static::getContainer()->get('form.factory'), static::getContainer()->get('doctrine')))
            ->createFormBuilder(new Comment(), [
                TextField::new('name', 'Name')->setColumns(6),
                EditorField::new('content', 'Text'),
            ], formOptions: ['csrf_protection' => false]);
        $builder->add('marker', HiddenType::class, ['mapped' => false]);
        if ($csrf) {
            // The field Symfony's FormTypeCsrfExtension adds: a HiddenType whose block prefix is csrf_token.
            $builder->add('_token', HiddenType::class, ['mapped' => false, 'block_prefix' => 'csrf_token', 'data' => 'token']);
        }

        $html = static::getContainer()->get('twig')->createTemplate(
            "{% form_theme form with ['@Admin/crud/form_theme.html.twig'] %}".
            "<div class=\"form-grid\">{% for child in form %}{% include '@Admin/crud/_form_child.html.twig' with {child: child} only %}{% endfor %}</div>"
        )->render(['form' => $builder->getForm()->createView()]);

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new \DOMXPath($document);
    }

    public function testTheEditorSpansTheFormWithItsLabel(): void
    {
        $grid = $this->grid();

        $rows = $grid->query('//div[contains(@class, "form-row")][.//*[@id="crud_form_content_editor"]]');
        $this->assertSame(1, $rows->length, 'the editor sits in a row of the grid');
        $this->assertStringContainsString('--col-span: 12', $rows->item(0)->getAttribute('style'), 'the whole width');
        $this->assertSame(1, $grid->query('.//label', $rows->item(0))->length, 'its label above it');
        $this->assertSame(0, $grid->query('//div[contains(@class, "form-grid")]/*[@id="crud_form_content_editor"]')->length, 'not a cell of the grid on its own');
    }

    public function testAFieldSpansItsColumnsAndAHiddenOneStaysBare(): void
    {
        $grid = $this->grid();

        $name = $grid->query('//div[contains(@class, "form-row")][.//input[@id="crud_form_name"]]');
        $this->assertSame(1, $name->length);
        $this->assertStringContainsString('--col-span: 6', $name->item(0)->getAttribute('style'));

        $this->assertSame(1, $grid->query('//div[contains(@class, "form-grid")]/input[@type="hidden"][@id="crud_form_marker"]')->length, 'the hidden input, bare: no row, no label');
    }

    public function testTheCsrfTokenStaysABareInput(): void
    {
        $grid = $this->grid(csrf: true);

        $token = $grid->query('//input[@type="hidden"][@id="crud_form__token"]');
        $this->assertSame(1, $token->length, 'the form carries its token');
        $this->assertSame(0, $grid->query('//div[contains(@class, "form-row")][.//input[@id="crud_form__token"]]')->length, 'no row of its own, no "Csrf token" label');
        $this->assertSame(1, $grid->query('//div[contains(@class, "form-grid")]/input[@id="crud_form__token"]')->length, 'the bare input in the grid');
    }
}
