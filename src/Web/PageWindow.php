<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Data\PageSize;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;

use function ceil;
use function count;
use function max;
use function min;

/**
 * Resolves the bounded slice of an already filtered and ordered collection and builds the paginator exposing it.
 */
final readonly class PageWindow
{
    /**
     * @var positive-int
     */
    public int $limit;
    /**
     * Index of the first row on the resolved page.
     */
    public int $offset;
    /**
     * @var positive-int
     */
    public int $page;
    /**
     * Total number of pages the collection spans.
     */
    public int $pageCount;

    /**
     * @param int $total Rows in the collection.
     * @param string|null $perPage Raw `per-page` query value, or `null` to keep every row on one page.
     * @param string|null $page Raw `page` query value, or `null` to start on the first page.
     */
    public function __construct(int $total, string|null $perPage, string|null $page)
    {
        $this->limit = PageSize::resolve($perPage) ?? max(1, $total);

        $this->pageCount = max(1, (int) ceil($total / $this->limit));
        $this->page = min($this->pageCount, max(1, (int) ($page ?? '1')));

        $this->offset = ($this->page - 1) * $this->limit;
    }

    /**
     * Builds the paginator positioned on the page the query values select.
     *
     * @template TRow of array<array-key, mixed>|object
     *
     * @param list<TRow> $rows Rows already filtered and ordered.
     * @param string|null $perPage Raw `per-page` query value, or `null` to keep every row on one page.
     * @param string|null $page Raw `page` query value, or `null` to start on the first page.
     *
     * @return OffsetPaginator<int, TRow> Paginator clamped to the resolved page.
     */
    public static function paginate(array $rows, string|null $perPage, string|null $page): OffsetPaginator
    {
        $window = new self(count($rows), $perPage, $page);

        return (new OffsetPaginator(new IterableDataReader($rows)))
            ->withPageSize($window->limit)
            ->withCurrentPage($window->page);
    }
}
