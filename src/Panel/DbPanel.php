<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\EmptyState;
use PHPForge\Debug\Panel\Db\{DbQueryRenderer, DbSnapshot, QueryRow};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Grid\{PrefixedDropdownFilter, PrefixedTextFilter};
use Yii3\Debug\Search\DbSearch;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function count;
use function htmlspecialchars;
use function is_int;
use function is_string;
use function number_format;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Presents the shared Database panel payload and contributes the query-count and total-time toolbar chips.
 */
final readonly class DbPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    /**
     * @param PanelGrid $grid Shared debugger grid renderer.
     */
    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'db';
    }

    public function id(): string
    {
        return 'db';
    }

    public function name(): string
    {
        return 'Database';
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
        $entries = DbSnapshot::fromArray($payload, 'panels.db')
            ->entries();

        $queryCount = count($entries);

        if ($queryCount === 0) {
            return [];
        }

        return [
            new ToolbarItem(
                value: (string) $queryCount,
                status: 'info',
                title: "Executed {$queryCount} database queries.",
            ),
            new ToolbarItem(value: self::totalTime($entries), title: 'Total query time'),
        ];
    }

    /**
     * Counts query rows marked as duplicated.
     *
     * @param list<QueryRow> $entries Captured query rows.
     */
    private static function duplicateCount(array $entries): int
    {
        $duplicates = 0;

        foreach ($entries as $entry) {
            if ($entry->duplicate > 1) {
                $duplicates++;
            }
        }

        return $duplicates;
    }

    /**
     * Renders the context-free fallback or the complete panel grid used by snapshot pages.
     *
     * @param array<string, mixed> $payload Decoded Database payload.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = DbSnapshot::fromArray($payload, 'panels.db')
            ->entries();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Database Queries')
            ->render();

        if ($entries === []) {
            return $title
                . EmptyState::card(
                    'No database queries captured',
                    P::tag()->content('This request did not profile queries through the debug DB profiler.'),
                );
        }

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = DbSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($entries);

        $summaryItems = [
            Span::tag()
                ->html(Strong::tag()
                ->content((string) count($entries)), ' queries'),
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()
                ->html(Strong::tag()
                ->content(self::summaryTime($entries)), ' ms total'),
        ];

        $duplicateCount = self::duplicateCount($entries);

        if ($duplicateCount > 0) {
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-warn')
                ->html(Strong::tag()
                ->content((string) $duplicateCount), ' duplicated');
        }

        if ($context !== null) {
            $summaryItems[] = $this->grid->pageSizeSelector($context->queryParams);
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$summaryItems);

        $traceLine = static function (array $frame): string {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? '';
            $text = is_string($file) ? $file . (is_string($line) || is_int($line) ? ":{$line}" : '') : '';

            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $full = $context !== null;
        $types = [];

        foreach ($entries as $entry) {
            $types[$entry->type] = $entry->type;
        }

        $columns = [
            new DataColumn(
                property: 'type',
                header: 'Type',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderTypeCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedDropdownFilter(FilterPrefix::DB, $types, ['aria-label' => 'Filter by Type'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
            new DataColumn(
                property: 'seq',
                header: 'Time',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: $full,
                bodyClass: 'yii-debug-cell-num yii-debug-nowrap',
            ),
            new DataColumn(
                property: 'duration',
                header: 'Duration',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderDurationCell($row),
                encodeContent: false,
                withSorting: $full,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                property: 'rows',
                header: 'Rows',
                content: static fn(QueryRow $row): string => DbQueryRenderer::renderRowsCell($row),
                encodeContent: false,
                withSorting: $full,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                property: 'duplicate',
                header: 'Dup',
                withSorting: $full,
                bodyClass: 'yii-debug-cell-num',
            ),
            new DataColumn(
                property: 'query',
                header: 'Query',
                content: fn(QueryRow $row): string => DbQueryRenderer::renderQueryCell(
                    $row,
                    $traceLine,
                    $context !== null,
                    fn(int $seq): string => $context?->actionUrl('db-explain', ['seq' => $seq]) ?? '',
                ),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::DB, ['aria-label' => 'Filter by Query'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
        ];

        if ($context === null) {
            return $title . $header->render() . $this->grid->render($columns, $filtered);
        }

        $grid = $this->grid
            ->fullForContext($context, FilterPrefix::DB, 'yii-debug-db-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-db'])
            ->dataReader(
                $this->grid->paginator(
                    $filtered,
                    $context->queryParams,
                    Sort::only(['duration', 'seq', 'type', 'query', 'duplicate', 'rows'])
                        ->withoutDefaultSorting()
                        ->withOrder(['seq' => 'asc']),
                ),
            )
            ->columns(...$columns)
            ->render();

        $explainAll = Div::tag()
            ->class('yii-debug-db-explain-all')
            ->html(A::tag()->href('javascript:;')->content('[+] Explain all'))
            ->render();

        return $title
            . $header->render()
            . $this->grid->activeFilterBanner($context, FilterPrefix::DB, $search->activeFilters)
            . $grid
            . $explainAll;
    }

    /**
     * Formats the summed query duration for the detail summary.
     *
     * @param list<QueryRow> $entries Captured query rows.
     */
    private static function summaryTime(array $entries): string
    {
        $total = 0.0;

        foreach ($entries as $entry) {
            $total += $entry->duration;
        }

        return number_format($total, 3);
    }

    /**
     * Formats the summed query duration as a millisecond readout.
     *
     * @param list<QueryRow> $entries Captured query rows.
     */
    private static function totalTime(array $entries): string
    {
        $total = 0.0;

        foreach ($entries as $entry) {
            $total += $entry->duration;
        }

        return number_format($total) . ' ms';
    }
}
