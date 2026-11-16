<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Config\Action;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Controller\Crud\RedirectionCrudController;
use Base\Admin\Filter\Filter;
use Base\Admin\Filter\Filters;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Parser;
use Base\Entity\Layout\Redirection;
use Base\Service\Model\Pagination;
use Base\Contract\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\Admin\ReleasesWhatItHolds;

/**
 * The list's query on the persistence chosen: its search, its filters (boolean, numeric with each comparison, text
 * contained or equal, a filter of its own by matchWith()), its order and its pages, read through the repository's
 * finder through omnibase's Paginator, give the rows and the total the redirections written here call for; the neighbouring record too.
 * Every list narrows itself to the rows written here with a filter of its own (matchWith()), so what else the table
 * holds does not count.
 */
class IndexQueryTest extends KernelTestCase
{
    use ReleasesWhatItHolds;

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
        $this->releaseWhatItHolds();
    }

    public function testTheListReadThroughTheFinderGivesTheRowsAsked(): void
    {
        $filters = $this->ours($this->filters());

        // [order, search, filter values, the rows written here expected, in order (their index in $this->written)]
        foreach ([
            'by hits, then id' => [['hits' => 'DESC', 'id' => 'ASC'], '', [], [0, 1, 2, 3, 4]],
            'by id, descending' => [['id' => 'DESC'], '', [], [4, 3, 2, 1, 0]],
            'by status, then id' => [['status' => 'ASC', 'id' => 'DESC'], '', [], [3, 0, 4, 2, 1]],
            'searched, whatever the case' => [['id' => 'ASC'], 'OLD-', [], [0, 1, 2]],
            'searched in the target' => [['id' => 'ASC'], 'new-', [], [0, 1, 2, 4]],
            'searched, nothing' => [['id' => 'ASC'], 'no-such-address', [], []],
            'enabled' => [['id' => 'ASC'], '', ['enabled' => '1'], [0, 1, 4]],
            'disabled' => [['id' => 'ASC'], '', ['enabled' => '0'], [2, 3]],
            'status equal' => [['id' => 'ASC'], '', ['status' => '302'], [1, 2, 4]],
            'status greater' => [['id' => 'ASC'], '', ['status' => ['comparison' => Filter::COMPARISON_GT, 'value' => '301']], [1, 2, 4]],
            'status lower' => [['id' => 'ASC'], '', ['status' => ['comparison' => Filter::COMPARISON_LT, 'value' => '302']], [0, 3]],
            'status not equal' => [['id' => 'ASC'], '', ['status' => ['comparison' => Filter::COMPARISON_NEQ, 'value' => '302']], [0, 3]],
            'target contained' => [['id' => 'ASC'], '', ['target' => 'NEW'], [0, 1, 2, 4]],
            'target equal' => [['id' => 'ASC'], '', ['target' => ['comparison' => Filter::COMPARISON_EQ, 'value' => '/new-a']], [0]],
            'target not equal' => [['id' => 'ASC'], '', ['target' => ['comparison' => Filter::COMPARISON_NEQ, 'value' => '/new-a']], [1, 2, 3, 4]],
            'searched and filtered' => [['id' => 'DESC'], 'old', ['enabled' => '1', 'status' => '302'], [1]],
        ] as $name => [$sort, $query, $values, $expected]) {
            $ids = array_map(fn (int $index) => $this->written[$index]->getId(), $expected);
            foreach ([1, 2, 3] as $page) {
                $list = $this->call('indexPage', $this->crud(2), $sort, $query, $filters, $values + ['ours' => '1'], $page);
                self::assertInstanceOf(Pagination::class, $list, $name);

                self::assertSame(\count($ids), $list->getTotalCount(), $name.': the total');
                self::assertSame(array_slice($ids, ($page - 1) * 2, 2), $this->ids($list), $name.', page '.$page);
                self::assertSame(max(1, (int) ceil(\count($ids) / 2)), (int) $list->getTotalPages(), $name);
            }
        }
    }

    public function testAFilterOfItsOwnSaysItsConditionWithMatchWith(): void
    {
        $tag = substr($this->written[0]->getSource(), 5, 6);
        $filters = $this->filters()->add(Filter::new('gone')->asBoolean()
            ->matchWith(static fn (mixed $value) => Criterion::of('source', '/gone-'.$tag.'%', Parser::OPTION_PARTIAL)));

        $page = $this->call('indexPage', $this->crud(20), ['id' => 'ASC'], '', $filters, ['gone' => '1'], 1);
        self::assertSame([$this->written[3]->getId(), $this->written[4]->getId()], $this->ids($page));

        // Inactive, it adds no condition.
        $page = $this->call('indexPage', $this->crud(200), ['id' => 'ASC'], '', $filters, [], 1);
        self::assertContains($this->written[0]->getId(), $this->ids($page));
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

    /** A filter of its own, always given: the rows written here only (their source holds the test's tag). */
    private function ours(Filters $filters): Filters
    {
        $tag = substr($this->written[0]->getSource(), 5, 6);

        return $filters->add(Filter::new('ours')->asBoolean()
            ->matchWith(static fn (mixed $value) => Criterion::of('source', '%-'.$tag.'-%', Parser::OPTION_PARTIAL)));
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
