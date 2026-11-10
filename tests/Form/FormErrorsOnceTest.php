<?php

namespace Tests\Base\Admin\Form;

use Base\Admin\Config\MenuItem;
use Base\Admin\Context\AdminContext;
use Base\Admin\Form\FieldFormBuilder;
use Base\Entity\Thread\Comment;
use Base\Field\TextField;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * A field's error is said once, at the field: nothing above the form reads as
 * one of its errors.
 *
 * The page's trail (crud/_page_heading.html.twig) printed its section as a
 * bare <li><span>The shop</span></li> - the shape of a form's error list
 * (form_errors) - so a page read for its errors, as omnibase/marketplace's
 * "without a script" tests read it (split on the grid's rows, an error list
 * before the first row is the form's own), found a form error "The shop"
 * beside the field's. Here the head and the grid are rendered as a form page
 * renders them, a section's list open, a field in error, and read the same way.
 */
class FormErrorsOnceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/things/new');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);

        // The page sits in a section of the menu, its list one of its members.
        static::getContainer()->get(AdminContext::class)->setMainMenu([
            MenuItem::section('The shop', 'fa-solid fa-store')->setSubItems([
                MenuItem::linkToUrl('Things', 'fa-solid fa-box', '/admin/things')->setLinkUrl('/admin/things')->setSelected(true),
            ]),
        ]);
    }

    private function page(): string
    {
        $builder = (new FieldFormBuilder(static::getContainer()->get('form.factory'), static::getContainer()->get(\Omnibridge\Persistence\UnitOfWork::class)))
            ->createFormBuilder(new Comment(), [
                TextField::new('name', 'Name')->setColumns(6),
            ], formOptions: ['csrf_protection' => false]);
        $builder->add('other', TextType::class, ['mapped' => false]);
        $form = $builder->getForm();
        $form->get('name')->addError(new FormError('Say a name.'));

        return static::getContainer()->get('twig')->createTemplate(
            "{% form_theme form with ['@Admin/crud/form_theme.html.twig'] %}".
            "<div class=\"admin-header\">{% include '@Admin/crud/_page_heading.html.twig' with {editable: false, pageKey: null, stored: null, fallbackTitle: 'Thing'} only %}</div>".
            "<div class=\"card\">{{ form_start(form) }}{{ form_errors(form) }}".
            "<div class=\"form-grid\">{% for child in form %}{% include '@Admin/crud/_form_child.html.twig' with {child: child} only %}{% endfor %}</div>".
            '{{ form_end(form, {render_rest: false}) }}</div>'
        )->render(['form' => $form->createView()]);
    }

    /**
     * The errors as a reader of the page finds them: each row of the grid's
     * error lists under its field's name, '' for a list before the first row.
     *
     * @return array<string, list<string>>
     */
    private static function errors(string $html): array
    {
        $errors = [];
        foreach (explode('<div class="form-row"', $html) as $n => $row) {
            if (!preg_match_all('~<li><span>(.*?)</span></li>~s', $row, $messages)) {
                continue;
            }
            $field = 0 === $n ? '' : (preg_match('~name="crud_form\[([^\]"]+)\]~', $row, $name) ? $name[1] : '?');
            $errors[$field] = array_map(static fn (string $message) => html_entity_decode(trim(strip_tags($message))), $messages[1]);
        }

        return $errors;
    }

    public function testTheTrailIsThereAndReadsAsNoError(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression('~<nav class="admin-breadcrumbs"[^>]*>.*The shop.*href="/admin/things".*Things.*</nav>~s', $html, 'the section, then the list as a link back');
        $this->assertSame(['name' => ['Say a name.']], self::errors($html), 'the field says its error, and nothing else reads as one');
    }

    public function testAFieldsErrorIsSaidOnceAtTheField(): void
    {
        $html = $this->page();

        $this->assertSame(1, substr_count($html, 'Say a name.'), 'once in the page');
        $rows = explode('<div class="form-row"', $html);
        $this->assertStringNotContainsString('Say a name.', $rows[0], 'not above the grid');
        $this->assertStringContainsString('name="crud_form[name]"', $rows[1]);
        $this->assertStringContainsString('name="crud_form[other]"', $rows[2]);
        $this->assertStringContainsString('Say a name.', $rows[1], 'in its own row');
        $this->assertStringNotContainsString('Say a name.', $rows[2], 'not in its neighbour');
    }
}
