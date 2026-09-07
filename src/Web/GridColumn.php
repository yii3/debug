<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use Stringable;
use Yiisoft\Yii\DataView\GridView\Column\Base\DataContext;
use Yiisoft\Yii\DataView\GridView\Column\ColumnInterface;

/**
 * Represents a panel grid column whose header, filter, and body content are pre-rendered by the caller.
 *
 * @template TRow of array|object
 */
final readonly class GridColumn implements ColumnInterface
{
    /**
     * @param string $header Header cell HTML, already rendered and encoded by the caller.
     * @param Closure(TRow, DataContext): string $content Builds the body cell content for one row.
     * @param string|Stringable|null $filter Filter cell content; `null` renders no filter cell at all, `''` renders an
     * empty cell that keeps the column class.
     * @param bool $encodeContent Whether the body content is plain text that the cell must encode.
     * @param string|null $class CSS class shared by the header, filter, and body cells, or `null` for none.
     * @param string|null $headerClass Extra CSS class for the header cell only, or `null` for none.
     * @param string|null $bodyClass Extra CSS class for the body cells only, or `null` for none.
     * @param array<string, string> $headerAttributes Extra header cell attributes, such as `aria-sort`.
     */
    public function __construct(
        public string $header,
        public Closure $content,
        public string|Stringable|null $filter = null,
        public bool $encodeContent = false,
        public string|null $class = null,
        public string|null $headerClass = null,
        public string|null $bodyClass = null,
        public array $headerAttributes = [],
    ) {}

    /**
     * @return class-string<GridColumnRenderer>
     */
    public function getRenderer(): string
    {
        return GridColumnRenderer::class;
    }

    public function isVisible(): bool
    {
        return true;
    }
}
