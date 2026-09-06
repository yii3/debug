<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Data\PageSize;

use function ceil;
use function max;
use function min;

/**
 * Resolves the bounded slice of an already filtered and ordered collection.
 */
final readonly class PageWindow
{
    public int $limit;
    public int $offset;
    public int $page;
    public int $pageCount;

    public function __construct(int $total, string|null $perPage, string|null $page)
    {
        $this->limit = PageSize::resolve($perPage) ?? max(1, $total);

        $this->pageCount = max(1, (int) ceil($total / $this->limit));
        $this->page = min($this->pageCount, max(1, (int) ($page ?? '1')));

        $this->offset = ($this->page - 1) * $this->limit;
    }
}
