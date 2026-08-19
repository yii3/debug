<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\{EmptyState, Format};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Profile\{ProfileCellRenderer, ProfileRow, ProfilingSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{P, Pre};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Grid\PrefixedTextFilter;
use Yii3\Debug\Search\ProfileSearch;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;

use function count;
use function is_float;
use function is_int;
use function number_format;
use function sprintf;

/**
 * Presents the shared Profiling panel payload and contributes the time and memory toolbar chips.
 */
final readonly class ProfilingPanel implements ContextAwarePanelInterface
{
    use PanelContentTrait;

    /**
     * @param PanelGrid|null $grid Shared grid renderer, or `null` for the backwards-compatible summary-only fallback.
     */
    public function __construct(private PanelGrid|null $grid = null) {}

    public function icon(): string
    {
        return 'profiling';
    }

    public function id(): string
    {
        return 'profiling';
    }

    public function name(): string
    {
        return 'Profiling';
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
        $time = $payload['time'] ?? null;
        $memory = $payload['memory'] ?? null;

        if ((!is_float($time) && !is_int($time)) || !is_int($memory)) {
            return [];
        }

        return [
            new ToolbarItem(value: self::formatTime((float) $time), title: 'Total processing time'),
            new ToolbarItem(value: Format::bytesToMb($memory, 3), title: 'Peak memory'),
        ];
    }

    /**
     * Formats a duration in seconds as a millisecond readout.
     *
     * @param float $seconds Duration in seconds.
     */
    private static function formatTime(float $seconds): string
    {
        return number_format($seconds * 1000) . ' ms';
    }

    /**
     * Renders the context-free fallback or the complete filterable profile grid used by snapshot pages.
     *
     * @param array<string, mixed> $payload Decoded Profiling payload.
     */
    private function renderPanel(array $payload, PanelRenderContext|null $context = null): string
    {
        $snapshot = ProfilingSnapshot::fromArray($payload, 'panels.profiling');

        $entries = $snapshot->entries();

        $title = H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Performance Profiling')
            ->render();
        $summaryItems = [
            Span::tag()
                ->html(
                    Strong::tag()
                        ->content(
                            self::formatTime($snapshot->time)
                        ),
                    ' total',
                ),
            Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·'),
            Span::tag()
                ->html(
                    Strong::tag()
                        ->content(Format::bytesToMb($snapshot->memory, 3)),
                    ' peak',
                ),
        ];

        if ($entries !== [] && $context !== null) {
            $summaryItems[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $summaryItems[] = A::tag()
                ->content('Open timeline')
                ->href($context->panelUrl('timeline', []));

            if ($this->grid !== null) {
                $summaryItems[] = $this->grid->pageSizeSelector($context->queryParams);
            }
        }

        $header = Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$summaryItems)
            ->render();

        if ($entries === []) {
            return $title
                . $header
                . EmptyState::card(
                    'No profile blocks captured',
                    P::tag()
                        ->html(
                            'This request did not produce any ',
                            Code::tag()->content('ProfilerInterface::begin()'),
                            ' / ',
                            Code::tag()->content('ProfilerInterface::end()'),
                            ' blocks, so the timing table is empty.',
                        ),
                    P::tag()->content('To populate this view, wrap interesting sections of code with profile markers:'),
                    Pre::tag()
                        ->class('yii-debug-empty-state-code')
                        ->content(
                            "\$profiler->begin('my-token');\n// …work…\n\$profiler->end('my-token');",
                        ),
                    P::tag()
                        ->content('Database queries are profiled automatically when the DB collector is configured.'),
                );
        }

        if ($this->grid === null) {
            return $title . $header . P::tag()
                ->content(sprintf('%d profile blocks captured.', count($entries)))
                ->render();
        }

        $queryParams = $context->queryParams ?? [];

        $search = ProfileSearch::fromQueryParams($queryParams);

        $filtered = $search->filter($entries);

        $full = $context !== null;

        $maxDuration = ProfileRow::maxDuration($entries);

        $columns = [
            new DataColumn(
                property: 'seq',
                header: 'Time',
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderTimeCell($row),
                encodeContent: false,
                withSorting: $full,
                headerClass: 'sort-numerical',
                bodyClass: 'yii-debug-cell-mono yii-debug-nowrap',
            ),
            new DataColumn(
                property: 'duration',
                header: 'Duration',
                columnAttributes: ['style' => 'width: 10%'],
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderDurationCell(
                    $row,
                    $maxDuration,
                ),
                encodeContent: false,
                withSorting: $full,
                headerClass: 'sort-numerical',
            ),
            new DataColumn(
                property: 'category',
                header: 'Category',
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderCategoryCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::PROFILE, ['aria-label' => 'Filter by Category'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
            new DataColumn(
                property: 'info',
                header: 'Info',
                columnAttributes: ['style' => 'width: 60%'],
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderInfoCell($row),
                encodeContent: false,
                withSorting: $full,
                filter: $full
                    ? new PrefixedTextFilter(FilterPrefix::PROFILE, ['aria-label' => 'Filter by Info'])
                    : false,
                filterEmpty: $full ? static fn(): bool => true : null,
            ),
        ];

        if ($context === null) {
            return $title . $header . $this->grid->render($columns, $filtered);
        }

        $grid = $this->grid
            ->fullForContext($context, FilterPrefix::PROFILE, 'yii-debug-profile-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-profile'])
            ->dataReader(
                $this->grid->paginator(
                    $filtered,
                    $context->queryParams,
                    Sort::only(['seq', 'duration', 'category', 'info'])
                        ->withoutDefaultSorting()
                        ->withOrder(['duration' => 'desc']),
                ),
            )
            ->columns(...$columns)
            ->render();

        return $title
            . $header
            . $this->grid->activeFilterBanner($context, FilterPrefix::PROFILE, $search->activeFilters)
            . $grid;
    }
}
