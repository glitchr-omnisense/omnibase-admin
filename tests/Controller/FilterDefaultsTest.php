<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filter;
use Base\Enum\ThreadState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Filters::add('createdAt') on a moment gives a date range, the core's utc_datetime(_immutable)
 * included (the guess knew Doctrine's own date types only: a text filter). A Thread's default
 * state filter offers the states by their value, labelled with the core's enum words and
 * "State" (it was keyed by French literals, 'Publié' => STATE_PUBLISH: the label was the value
 * the list was filtered on).
 */
final class FilterDefaultsTest extends KernelTestCase
{
    public function testEveryMomentIsADateRange(): void
    {
        $guess = new \ReflectionMethod(AbstractCrudController::class, 'guessFilterType');
        foreach (['date', 'datetime', 'datetime_immutable', 'datetimetz', 'utc_datetime', 'utc_datetime_immutable'] as $type) {
            $filter = Filter::new('createdAt');
            $guess->invoke(null, $filter, $type);
            self::assertSame(Filter::TYPE_DATE, $filter->getType(), $type);
        }
        $filter = Filter::new('title');
        $guess->invoke(null, $filter, 'string');
        self::assertSame(Filter::TYPE_TEXT, $filter->getType());
    }

    public function testAThreadsStateFilterIsByValueAndTranslated(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists(\Base\Forum\Controller\Admin\Crud\TopicCrudController::class)) {
            self::markTestSkipped('Requires the omnibase harness with omnibase/forum (a CRUD of a Thread).');
        }
        self::bootKernel();
        $request = Request::create('/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $controller = static::getContainer()->get(\Base\Forum\Controller\Admin\Crud\TopicCrudController::class);
        $state = (new \ReflectionMethod($controller, 'getFiltersConfig'))->invoke($controller)->get('state');

        self::assertSame('State', $state->getLabel());
        self::assertSame([ThreadState::PUBLISH, ThreadState::DRAFT, ThreadState::FUTURE, ThreadState::SECRET, ThreadState::ARCHIVE], array_keys($state->getChoices()));
        $translator = static::getContainer()->get('translator');
        foreach (['en' => 'published', 'fr' => 'publié'] as $locale => $word) {
            self::assertSame($word, $translator->trans($state->getChoices()[ThreadState::PUBLISH], [], null, $locale), $locale);
        }
    }
}
