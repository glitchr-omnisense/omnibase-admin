<?php

namespace Base\Admin\Index;

use Base\Database\Repository\Entities;

/**
 * One page of a list, on the persistence the site chose (omnibase's plan "omnibase agnostic", step B1b): the rows of
 * the page through the repository's findBy() - the criteria of the finder language (Base\Database\Repository\Finder:
 * the search and the filters as groups), the order, the page's size as the limit and its offset -, the total through
 * its count() on the same criteria. omnibridge/doctrine and omnibridge/eloquent compile both; the counters are those
 * the index template reads, as Base\Admin\Orm\Paginator (Doctrine's Paginator, kept for a list built on a QueryBuilder)
 * gives them.
 *
 * @implements \IteratorAggregate<int, object>
 */
class RecordPage implements \IteratorAggregate, \Countable
{
    protected int $page;
    protected int $pageSize;
    /** @var list<object>|null */
    protected ?array $rows = null;
    protected ?int $total = null;

    /**
     * @param Entities<object>      $entities the repository of the list's class (Repositories::of())
     * @param array<int|string, mixed> $criteria the finder's criteria (groups, keys, field => value)
     * @param array<string, string> $order    field (or path) => ASC|DESC
     */
    public function __construct(
        protected readonly Entities $entities,
        protected readonly array $criteria,
        protected readonly array $order,
        int $page = 1,
        int $pageSize = 20,
    ) {
        $this->page = max(1, $page);
        $this->pageSize = max(1, $pageSize);
    }

    /** @return list<object> the page's rows */
    public function getRows(): array
    {
        return $this->rows ??= array_values($this->entities->findBy($this->criteria, [] === $this->order ? null : $this->order, $this->pageSize, ($this->page - 1) * $this->pageSize));
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getRows());
    }

    public function count(): int
    {
        return \count($this->getRows());
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getTotal(): int
    {
        return $this->total ??= $this->entities->count($this->criteria);
    }

    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->getTotal() / $this->pageSize));
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getPageCount();
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->page - 1);
    }

    public function getNextPage(): int
    {
        return min($this->getPageCount(), $this->page + 1);
    }
}
