<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\{EmptyState, LogLevel};
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogCounts, LogRow, LogSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\P;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Grid\{PrefixedDropdownFilter, PrefixedTextFilter};
use Yii3\Debug\Search\LogSearch;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function htmlspecialchars;
use function is_int;
use function is_string;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Presents the shared Logs panel payload and contributes the message-count toolbar chips.
 */
final readonly class LogPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    /**
     * @param PanelGrid $grid Shared debugger grid renderer.
     */
    public function __construct(private PanelGrid $grid) {}

    public function icon(): string
    {
        return 'logs';
    }

    public function id(): string
    {
        return 'log';
    }

    public function name(): string
    {
        return 'Logs';
    }

    public function render(array $payload): string
    {
        return $this->renderPanel(
            $payload,
        );
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel(
            $payload,
            $context,
        );
    }

    public function toolbarItems(array $payload): array
    {
        $counts = LogCounts::fromRows(LogSnapshot::fromArray($payload, 'panels.log')->entries());

        $items = [new ToolbarItem(value: (string) $counts->total, id: 'total')];

        if ($counts->errors > 0) {
            $items[] = new ToolbarItem(
                value: (string) $counts->errors,
                label: 'Errors',
                status: 'danger',
                id: 'errors',
            );
        }

        if ($counts->warnings > 0) {
            $items[] = new ToolbarItem(
                value: (string) $counts->warnings,
                label: 'Warnings',
                status: 'warning',
                id: 'warnings',
            );
        }

        return $items;
    }

    /**
     * Renders the context-free fallback or the complete panel grid used by snapshot pages.
     *
     * @param array<string, mixed> $payload Decoded Logs payload.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $entries = LogSnapshot::fromArray($payload, 'panels.log')
            ->entries();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Log Messages')
            ->render();

        if ($entries === []) {
            return $title
                . EmptyState::card(
                    'No log messages captured',
                    P::tag()
                        ->content('This request did not emit log messages through the debug log target.'),
                );
        }

        $queryParams = $context === null ? [] : $context->queryParams;

        $search = LogSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($entries);

        $counts = LogCounts::fromRows($entries);

        $summaryItems = [
            Span::tag()
                ->html(
                    Strong::tag()
                ->content(
                    (string) $counts->total
                ),
                    ' messages',
                ),
        ];

        if ($counts->hasErrors()) {
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-danger')
                ->html(
                    Strong::tag()
                        ->content((string) $counts->errors),
                    ' errors',
                );
        }

        if ($counts->hasWarnings()) {
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-warn')
                ->html(
                    Strong::tag()
                        ->content((string) $counts->warnings),
                    ' warnings',
                );
        }

        if ($counts->hasInfo()) {
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $summaryItems[] = Span::tag()
                ->html(
                    Strong::tag()
                ->content(
                    (string) $counts->info
                ),
                    ' info',
                );
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
        $columns = [
            new DataColumn(
                property: 'id',
                header: '#',
                withSorting: false,
                bodyClass: 'yii-debug-nowrap',
            ),
            new DataColumn(
                property: 'time',
                header: 'Time',
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: $full,
                bodyClass: 'yii-debug-nowrap',
            ),
            new DataColumn(
                property: 'timeSincePrevious',
                header: 'Since previous',
                content: static fn(LogRow $row): string => LogCellRenderer::renderTimeSincePreviousCell($row),
                encodeContent: false,
                withSorting: $full,
            ),
            new DataColumn(
                property: 'level',
                header: 'Level',
                content: static fn(LogRow $row): string => LogCellRenderer::renderLevelCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedDropdownFilter(
                        FilterPrefix::LOG,
                        [
                            LogLevel::TRACE => ' Trace ',
                            LogLevel::INFO => ' Info ',
                            LogLevel::WARNING => ' Warning ',
                            LogLevel::ERROR => ' Error ',
                        ],
                        ['aria-label' => 'Filter by Level'],
                    )
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
            new DataColumn(
                property: 'category',
                header: 'Category',
                content: static fn(LogRow $row): string => LogCellRenderer::renderCategoryCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::LOG, ['aria-label' => 'Filter by Category'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
            new DataColumn(
                property: 'message',
                header: 'Message',
                content: static fn(LogRow $row): string => LogCellRenderer::renderMessageCell($row, $traceLine),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::LOG, ['aria-label' => 'Filter by Message'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
        ];

        if ($context === null) {
            return $title . $header->render() . $this->grid->render($columns, $filtered);
        }

        $grid = $this->grid
            ->fullForContext($context, FilterPrefix::LOG, 'yii-debug-log-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-log'])
            ->bodyRowAttributes(
                static fn(array|object $row): array => $row instanceof LogRow
                    ? LogCellRenderer::buildRowOptions($row)
                    : [],
            )
            ->dataReader(
                $this->grid->paginator(
                    $filtered,
                    $context->queryParams,
                    Sort::only(['time', 'timeSincePrevious', 'level', 'category', 'message'])
                        ->withoutDefaultSorting()
                        ->withOrder(['time' => 'asc']),
                ),
            )
            ->columns(...$columns)
            ->render();

        return $title
            . $header->render()
            . $this->grid->activeFilterBanner($context, FilterPrefix::LOG, $search->activeFilters)
            . $grid;
    }
}
