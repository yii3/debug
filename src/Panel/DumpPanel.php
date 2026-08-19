<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Dump\{DumpCardRenderer, DumpRow, DumpSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{P, Pre};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Grid\PrefixedTextFilter;
use Yii3\Debug\Search\DumpSearch;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function count;
use function htmlspecialchars;
use function is_array;
use function is_int;
use function is_string;
use function spl_object_id;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Presents explicitly captured Yii3 values with the shared Dump cards and filterable grid.
 */
final readonly class DumpPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'dump';
    }

    public function id(): string
    {
        return 'dump';
    }

    public function name(): string
    {
        return 'Dump';
    }

    public function render(array $payload): string
    {
        return $this->renderPanel($payload);
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel($payload, $context);
    }

    public function toolbarItems(array $payload): array
    {
        $entries = $payload['entries'] ?? null;

        if (!is_array($entries) || $entries === []) {
            return [];
        }

        return [
            new ToolbarItem(
                value: (string) count($entries),
                status: 'info',
                title: 'Number of dumped variables',
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = DumpSnapshot::fromArray($payload, 'panels.dump')
            ->entries();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Dump')
            ->render();

        if ($entries === []) {
            return $title
                . Header::tag()
                    ->class('yii-debug-grid-summary')
                    ->html(
                        Span::tag()
                            ->html(
                                Strong::tag()
                                    ->content('0'),
                                ' dumps captured',
                            ),
                        )
                    ->render()
                . EmptyState::card(
                    'No variables dumped in this request',
                    P::tag()->html(
                        'The Dump panel records values passed to ',
                        Code::tag()->content('DumpCollector::collect()'),
                        ', so nothing was captured here.',
                    ),
                    P::tag()->content('Inject the collector and capture values anywhere in the request cycle:'),
                    Pre::tag()
                        ->class('yii-debug-empty-state-code')
                        ->content(
                            "\$dumpCollector->collect(\$userData);\n"
                            . "\$dumpCollector->collect(\$query, 'database');",
                        ),
                );
        }

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = DumpSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($entries);

        $summaryItems = [
            Span::tag()
                ->html(
                    Strong::tag()->content((string) count($filtered)),
                    ' dumps captured',
                ),
        ];

        if ($context !== null) {
            $summaryItems[] = $this->grid->pageSizeSelector($queryParams);
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$summaryItems)
            ->render();

        $traceLine = static function (array $frame): string {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? '';
            $text = is_string($file) ? $file . (is_string($line) || is_int($line) ? ":{$line}" : '') : '';

            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $indexes = [];

        foreach ($entries as $index => $entry) {
            $indexes[spl_object_id($entry)] = $index;
        }

        $full = $context !== null;
        $columns = [
            new DataColumn(
                property: 'category',
                header: 'Category',
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::LOG, ['aria-label' => 'Filter by Category'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-mono yii-debug-muted yii-debug-nowrap',
            ),
            new DataColumn(
                property: 'message',
                header: 'Message',
                content: static fn(DumpRow $row): string => DumpCardRenderer::renderMessageCell(
                    $row,
                    $traceLine,
                    $indexes[spl_object_id($row)] ?? 0,
                ),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::LOG, ['aria-label' => 'Filter by Message'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
        ];

        if ($context === null) {
            return $title . $header . $this->grid->render($columns, $filtered);
        }

        $grid = $this->grid
            ->fullForContext($context, FilterPrefix::LOG, 'yii-debug-dump-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-dump'])
            ->dataReader(
                $this->grid->paginator(
                    $filtered,
                    $queryParams,
                    Sort::only(['category', 'message', 'time'])->withoutDefaultSorting(),
                ),
            )
            ->columns(...$columns)
            ->render();

        return $title
            . $header
            . $this->grid->activeFilterBanner($context, FilterPrefix::LOG, $search->activeFilters)
            . $grid;
    }
}
