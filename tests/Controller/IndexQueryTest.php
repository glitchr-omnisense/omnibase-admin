<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Config\Action;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Controller\Crud\RedirectionCrudController;
use Base\Admin\Filter\Filter;
use Base\Admin\Filter\Filters;
use Base\Admin\Index\RecordPage;
use Base\Admin\Orm\Paginator;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Entity\Layout\Redirection;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The list's query on the persistence chosen (omnibase's plan "omnibase agnostic", step B1b): its search, its filters
 * (boolean, numeric with each comparison, text contained or equal, a filter of its own by matchWith()), its order and its
 * pages read through the repository's finder (RecordPage) give the rows and the total that the former query on
 * Doctrine's QueryBuilder (createIndexQueryBuilder(), applySearch(), applyFilters(), Doctrine's Paginator) gives, on the
 * redirections' CRUD; the neighbouring record too. The former query stays where the finder cannot say the list: under
 * omnisense/omnibase 4.x (no Group::of()), and - deprecated - for an active filter given only applyWith().
 */
class IndexQueryTest extends KernelTestCase
{
    private AbstractCrudController $controller;

    /** @var list<Redirection> */
    private array $written = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/redirections');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $this->controller = static::getContainer()->get(RedirectionCrudController::class);

        $unitOfWork = static::getContainer()->get(UnitOfWork::class);
        $tag = substr(md5(uniqid('', true)), 0, 6);
        foreach ([
            ['/old-'.$tag.'-a', '/new-a', 301, true],
            ['/old-'.$tag.'-b', '/NEW-b', 302, true],
            ['/Old-'.$tag.'-c', '/new-c', 302, false],
            ['/gone-'.$tag.'-d', '/elsewhere', 301, false],
            ['/gone-'.$tag.'-e', '/new-e', 302, true],
        ] as [$source, $target, $status, $enabled]) {
            $redirection = (new Redirection($source, $target, $status))->setEnabled($enabled);
            $unitOfWork->persist($redirection);
            $this->written[] = $redirection;
        }
        $unitOfWork->flush();
    }

    protected function tearDown(): void
    {
        if ($this->written) {
            $unitOfWork = static::getContainer()->get(UnitOfWork::class);
            $redirections = static::getContainer()->get(\Base\Database\Repository\Repositories::class)->of(Redirection::class);
            foreach ($this->written as $redirection) {
                $unitOfWork->remove($redirections->find($redirection->getId()) ?? $redirection);
            }
            $unitOfWork->flush();
            $this->written = [];
        }
        parent::tearDown();
    }

    public function testTheListReadThroughTheFinderIsTheFormerQuerysOnDoctrine(): void
    {
        if (!method_exists(Group::class, 'of')) {
            self::markTestSkipped('omnisense/omnibase without Group::of() (4.x): the list is built on Doctrine, as before.');
        }
        $ours = array_map(static fn (Redirection $redirection) => $redirection->getId(), $this->written);
        $filters = $this->filters();

        foreach ([
            'default order' => [[], '', []],
            'by source, descending' => [['source' => 'DESC'], '', []],
            'by status, then id' => [['status' => 'ASC', 'id' => 'DESC'], '', []],
            'searched, whatever the case' => [['id' => 'ASC'], 'OLD-', []],
            'searched in the target' => [['id' => 'ASC'], 'new-', []],
            'searched, nothing' => [['id' => 'ASC'], 'no-such-address', []],
            'enabled' => [['id' => 'ASC'], '', ['enabled' => '1']],
            'disabled' => [['id' => 'ASC'], '', ['enabled' => '0']],
            'status equal' => [['id' => 'ASC'], '', ['status' => '302']],
            'status greater' => [['id' => 'ASC'], '', ['status' => ['comparison' => Filter::COMPARISON_GT, 'value' => '301']]],
            'status lower' => [['id' => 'ASC'], '', ['status' => ['comparison' => Filter::COMPARISON_LT, 'value' => '302']]],
            'status not equal' => [['id' => 'ASC'], '', ['status' => ['comparison' => Filter::COMPARISON_NEQ, 'value' => '302']]],
            'target contained' => [['id' => 'ASC'], '', ['target' => 'NEW']],
            'target equal' => [['id' => 'ASC'], '', ['target' => ['comparison' => Filter::COMPARISON_EQ, 'value' => '/new-a']]],
            'target not equal' => [['id' => 'ASC'], '', ['target' => ['comparison' => Filter::COMPARISON_NEQ, 'value' => '/new-a']]],
            'searched and filtered' => [['id' => 'DESC'], 'old', ['enabled' => '1', 'status' => '302']],
        ] as $name => [$sort, $query, $values]) {
            foreach ([1, 2, 3] as $page) {
                $crud = $this->crud(2);
                $finder = $this->call('indexPage', $crud, $sort ?: $crud->getDefaultSort(), $query, $filters, $values, $page);
                self::assertInstanceOf(RecordPage::class, $finder, $name);
                $former = $this->formerPage($crud, $sort ?: $crud->getDefaultSort(), $query, $filters, $values, $page);

                self::assertSame($former->getTotal(), $finder->getTotal(), $name.': the total');
                self::assertSame($this->ids($former), $this->ids($finder), $name.', page '.$page);
                self::assertSame($former->getPageCount(), $finder->getPageCount(), $name);
                self::assertSame($former->hasNextPage(), $finder->hasNextPage(), $name);
            }
        }

        // The rows are ours where the list is narrowed to them.
        $crud = $this->crud(20);
        $enabled = $this->ids($this->call('indexPage', $crud, ['id' => 'ASC'], 'old', $filters, ['enabled' => '1'], 1));
        self::assertSame([$ours[0], $ours[1]], $enabled);
    }

    public function testAFilterOfItsOwnSaysItsConditionWithMatchWith(): void
    {
        if (!method_exists(Group::class, 'of')) {
            self::markTestSkipped('omnisense/omnibase without Group::of() (4.x).');
        }
        $tag = substr($this->written[0]->getSource(), 5, 6);
        $filters = $this->filters()->add(Filter::new('gone')->asBoolean()
            ->applyWith(static fn ($queryBuilder, string $alias, mixed $value) => $queryBuilder->andWhere($alias.'.source LIKE :gone')->setParameter('gone', '/gone-%'))
            ->matchWith(static fn (mixed $value) => Criterion::of('source', '/gone-'.$tag.'%', Parser::OPTION_PARTIAL)));

        $page = $this->call('indexPage', $this->crud(20), ['id' => 'ASC'], '', $filters, ['gone' => '1'], 1);
        self::assertInstanceOf(RecordPage::class, $page);
        self::assertSame([$this->written[3]->getId(), $this->written[4]->getId()], $this->ids($page));
    }

    public function testAnActiveFilterGivenOnlyApplyWithKeepsTheFormerQuery(): void
    {
        $filters = $this->filters()->add(Filter::new('gone')->asBoolean()
            ->applyWith(static fn ($queryBuilder, string $alias, mixed $value) => $queryBuilder->andWhere($alias.'.source LIKE :gone')->setParameter('gone', '/gone-%')));

        $page = $this->call('indexPage', $this->crud(20), ['id' => 'ASC'], '', $filters, ['gone' => '1'], 1);
        self::assertInstanceOf(Paginator::class, $page);
        self::assertContains($this->written[4]->getId(), $this->ids($page));
        self::assertNotContains($this->written[0]->getId(), $this->ids($page));

        // Inactive, it does not stand in the finder's way.
        $page = $this->call('indexPage', $this->crud(20), ['id' => 'ASC'], '', $filters, [], 1);
        self::assertInstanceOf(method_exists(Group::class, 'of') ? RecordPage::class : Paginator::class, $page);
    }

    public function testTheNeighbouringRecordIsTheNextIdEitherWay(): void
    {
        [$first, $second, $third] = $this->written;
        $url = fn (Redirection $redirection, string $direction) => $this->call('adjacentEntityUrl', $redirection, $direction, 'edit');

        self::assertStringContainsString('/'.$first->getId().'/', $url($second, 'next'));
        self::assertStringContainsString('/'.$third->getId().'/', $url($second, 'prev'));
        $last = end($this->written);
        self::assertSame('', $url($last, 'prev'), 'nothing above the newest');
    }

    private function filters(): Filters
    {
        return $this->call('getFiltersConfig')->add('target');
    }

    private function crud(int $pageSize): Crud
    {
        return $this->call('getCrudConfig', Crud::PAGE_INDEX, Action::INDEX)->setPaginatorPageSize($pageSize);
    }

    /** The former query: Doctrine's QueryBuilder, its search and filters, Doctrine's Paginator. */
    private function formerPage(Crud $crud, array $sort, string $query, Filters $filters, array $values, int $page): Paginator
    {
        $queryBuilder = $this->controller->createIndexQueryBuilder($crud, $sort);
        if ('' !== $query) {
            $this->call('applySearch', $queryBuilder, $crud, $query);
        }
        $this->call('applyFilters', $queryBuilder, $filters, $values);

        return new Paginator($queryBuilder, $page, $crud->getPaginatorPageSize());
    }

    /** @return list<int> */
    private function ids(iterable $page): array
    {
        $ids = [];
        foreach ($page as $redirection) {
            $ids[] = $redirection->getId();
        }

        return $ids;
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod($this->controller, $method))->invoke($this->controller, ...$arguments);
    }
}
