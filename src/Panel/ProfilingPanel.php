<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{EmptyState, Format};
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\{MemorySample, PanelIcon, PanelRenderContext, PanelTitle};
use PHPForge\Debug\Panel\Profile\{ProfileCellRenderer, ProfileMessage, ProfileRow, ProfilingSnapshot};
use PHPForge\Debug\Panel\Timeline\{TimelineGeometry, TimelineMemoryRenderer, TimelineRenderer};
use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputNumber, InputText};
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Label};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Search\ProfileSearch;
use Yii3\Debug\Web\{
    FilterInput,
    FilterRemoval,
    GridColumn,
    GridFooter,
    PageWindow,
    PanelHeading,
    SortState,
    SummaryChip,
};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\GridView;

use function count;
use function str_replace;
use function strcasecmp;

/**
 * Presents captured profiling spans and contributes the processing-time and peak-memory toolbar metrics.
 */
final readonly class ProfilingPanel implements
    ContextAndSummaryAwarePanelInterface,
    ContextAwarePanelInterface,
    ToolbarPanelProviderInterface,
    ToolbarTitleProviderInterface
{
    private const array SORT_ATTRIBUTES = ['seq', 'duration', 'category', 'info'];

    public function hasContent(array $payload): bool
    {
        self::snapshot($payload);

        return true;
    }

    public function icon(): string
    {
        return PanelIcon::PROFILING->value;
    }

    public function id(): string
    {
        return 'profiling';
    }

    public function name(): string
    {
        return PanelTitle::PROFILING->value;
    }

    public function render(array $payload): string
    {
        return $this->renderPanel($payload);
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel($payload, $context);
    }

    public function renderWithContextAndSummary(
        array $payload,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string {
        return $this->renderPanel($payload, $context, $summary);
    }

    public function toolbarItems(array $payload): array
    {
        $snapshot = self::snapshot($payload);

        return [
            ToolbarItem::create(Format::milliseconds($snapshot->time))->withTitle('Total processing time'),
            ToolbarItem::create(Format::bytesToMb($snapshot->memory, 3))->withTitle('Peak memory'),
        ];
    }

    public function toolbarTitle(): string
    {
        return '';
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<string, string> $filters
     *
     * @return list<GridColumn<ProfileRow>>
     */
    private static function columns(
        float $maxDuration,
        PanelRenderContext|null $context,
        array $queryParams,
        array $filters,
        bool $renderFilters,
    ): array {
        $filterCells = $context !== null && $renderFilters;

        return [
            new GridColumn(
                header: self::header('seq', 'Time', $context, $queryParams),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderTimeCell($row),
                filter: $filterCells ? '' : null,
                bodyClass: 'yii-debug-cell-mono yii-debug-nowrap',
            ),
            new GridColumn(
                header: self::header('duration', 'Duration', $context, $queryParams),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderDurationCell(
                    $row,
                    $maxDuration,
                ),
                filter: $filterCells ? '' : null,
            ),
            new GridColumn(
                header: self::header('category', 'Category', $context, $queryParams),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderCategoryCell($row),
                filter: $filterCells
                    ? FilterInput::text(FilterPrefix::PROFILE, 'category', 'Category', $filters)
                    : null,
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
            new GridColumn(
                header: self::header('info', 'Info', $context, $queryParams),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderInfoCell($row),
                filter: $filterCells
                    ? FilterInput::text(FilterPrefix::PROFILE, 'info', 'Info', $filters)
                    : null,
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function filterHiddenParams(PanelRenderContext $context): array
    {
        $params = [
            'tag' => $context->tag,
            'panel' => $context->panel,
        ];

        foreach (['sort', 'per-page', 'yii_debug_theme'] as $name) {
            $value = QueryInput::scalar($context->queryParams, $name);

            if ($value !== null && $value !== '') {
                $params[$name] = $value;
            }
        }

        return $params;
    }

    /**
     * Renders the header cell content as a sort link, or as the plain label for context-free rendering.
     *
     * @param array<array-key, mixed> $queryParams
     */
    private static function header(
        string $attribute,
        string $label,
        PanelRenderContext|null $context,
        array $queryParams,
    ): string {
        if ($context === null) {
            return $label;
        }

        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            self::SORT_ATTRIBUTES,
            'duration',
            'desc',
        );

        unset($queryParams['page']);

        $isActive = $state->isActive($attribute);
        $queryParams['sort'] = $state->next($attribute);

        $link = A::tag()
            ->href($context->panelUrl(queryParams: $queryParams))
            ->content($label);

        return ($isActive ? $link->class($state->direction) : $link)->render();
    }

    private static function renderEmptyState(): string
    {
        return EmptyState::card(
            ProfileMessage::EMPTY_HEADLINE->value,
            P::tag()
                ->html(
                    'This request did not produce any ',
                    Code::tag()->content('ProfilerInterface::begin()'),
                    ' / ',
                    Code::tag()->content('ProfilerInterface::end()'),
                    ' spans, so the Timeline and details are empty.',
                ),
            P::tag()->content(ProfileMessage::EMPTY_CALL_TO_ACTION),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content(ProfileMessage::EMPTY_EXAMPLE),
            P::tag()->content(ProfileMessage::EMPTY_DB_NOTE),
        );
    }

    private static function renderFilterForm(PanelRenderContext $context, ProfileSearch $search): string
    {
        $children = [];

        foreach (self::filterHiddenParams($context) as $name => $value) {
            $children[] = InputHidden::tag()->name($name)->value($value);
        }

        $children[] = Div::tag()
            ->class('yii-debug-tl-field')
            ->html(
                Label::tag()
                    ->content('Min duration (ms)')
                    ->for('profile-duration'),
                InputNumber::tag()
                    ->id('profile-duration')
                    ->min(0)
                    ->name(FilterPrefix::PROFILE . '[duration]')
                    ->placeholder('0')
                    ->step(0.1)
                    ->value($search->duration()),
            );
        $children[] = Div::tag()
            ->class('yii-debug-tl-field yii-debug-tl-field-grow')
            ->html(
                Label::tag()
                    ->content('Category')
                    ->for('profile-category'),
                InputText::tag()
                    ->id('profile-category')
                    ->name(FilterPrefix::PROFILE . '[category]')
                    ->placeholder('yii\\db\\Command::query')
                    ->value($search->category()),
            );
        $children[] = Div::tag()
            ->class('yii-debug-tl-field yii-debug-tl-field-grow')
            ->html(
                Label::tag()
                    ->content('Info')
                    ->for('profile-info'),
                InputText::tag()
                    ->id('profile-info')
                    ->name(FilterPrefix::PROFILE . '[info]')
                    ->placeholder('SELECT')
                    ->value($search->info()),
            );
        $children[] = Button::tag()
            ->class('yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm')
            ->content('Apply')
            ->type('submit');

        return Form::tag()
            ->action($context->panelUrl(queryParams: []))
            ->addAriaAttribute('label', 'Profiling filters')
            ->class('yii-debug-tl-filter')
            ->html(...$children)
            ->method('get')
            ->render();
    }

    /**
     * @param OffsetPaginator<int, ProfileRow> $paginator
     * @param array<string, string> $filters
     */
    private static function renderGrid(
        OffsetPaginator $paginator,
        float $maxDuration,
        PanelRenderContext|null $context = null,
        array $filters = [],
        bool $renderFilters = true,
    ): string {
        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::PROFILE,
                $filters,
                ['view', FilterPrefix::TIMELINE],
            );

        /** @var GridView<ProfileRow> $grid */
        $grid = GridView::widget();

        $grid = $grid
            ->dataReader($paginator)
            ->layout('{items}')
            ->containerClass('yii-debug-table-wrap')
            ->tableClass('yii-debug-table')
            ->headerCellAttributes(['scope' => 'col'])
            ->filterCellAttributes(['class' => 'yii-debug-filter-cell'])
            ->filterFormId('yii-debug-profile-filters')
            ->columns(...self::columns($maxDuration, $context, $queryParams, $filters, $renderFilters));

        if ($context !== null) {
            $grid = $grid->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));
        }

        $footer = GridFooter::renderForPanel($paginator, $paginator->getCurrentPageSize(), $context, $queryParams);

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-profile')
            ->html($grid->render(), $footer)
            ->render();
    }

    private static function renderNoMatchState(): string
    {
        return EmptyState::card(
            ProfileMessage::NO_MATCH_HEADLINE->value,
            P::tag()->content(ProfileMessage::NO_MATCH_EXPLANATION),
        );
    }

    /**
     * @param list<ProfileRow> $filteredRows
     * @param list<ProfileRow> $entries
     * @param array<string, string> $filters
     */
    private static function renderPaginatedGrid(
        array $filteredRows,
        array $entries,
        PanelRenderContext $context,
        array $filters = [],
        bool $renderFilters = true,
    ): string {
        $queryParams = FilterRemoval::withGroup(
            $context->queryParams,
            FilterPrefix::PROFILE,
            $filters,
            ['view', FilterPrefix::TIMELINE],
        );

        $sortedRows = self::sortRows($filteredRows, QueryInput::scalar($queryParams, 'sort'));

        $paginator = PageWindow::paginate(
            $sortedRows,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return self::renderGrid(
            $paginator,
            ProfileRow::maxDuration($entries),
            $context,
            $filters,
            $renderFilters,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderPanel(
        array $payload,
        PanelRenderContext|null $context = null,
        RequestSummary|null $summary = null,
    ): string {
        $snapshot = self::snapshot($payload);

        $title = PanelHeading::render(PanelTitle::PROFILING_DETAILS);

        if ($context === null || $summary === null) {
            return $title . $this->renderProfilingView($snapshot, $context);
        }

        return $title . self::renderUnifiedView($snapshot, $context, $summary);
    }

    private function renderProfilingView(
        ProfilingSnapshot $snapshot,
        PanelRenderContext|null $context,
    ): string {
        $entries = $snapshot->entries();

        $search = ProfileSearch::fromQueryParams($context->queryParams ?? []);

        $queryParams = $context === null
            ? []
            : FilterRemoval::withGroup(
                $context->queryParams,
                FilterPrefix::PROFILE,
                $search->activeFilters,
                ['view', FilterPrefix::TIMELINE],
            );

        $filteredRows = $search->filter($entries);

        $content = self::renderSummary(
            count($filteredRows),
            count($entries),
            $snapshot,
            $context === null || $filteredRows === [] ? null : PageSize::selectorFor($queryParams),
        );

        if ($entries === []) {
            return $content . self::renderEmptyState();
        }

        if ($context === null) {
            return $content . self::renderGrid(
                PageWindow::single($filteredRows),
                ProfileRow::maxDuration($entries),
            );
        }

        $filterBanner = FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::PROFILE);

        if ($filteredRows === []) {
            return $content . $filterBanner . self::renderNoMatchState();
        }

        return "{$content}{$filterBanner}"
            . self::renderPaginatedGrid($filteredRows, $entries, $context, $search->activeFilters);
    }

    private static function renderSummary(
        int $filteredCount,
        int $totalCount,
        ProfilingSnapshot $snapshot,
        string|null $pageSizeSelector,
        int $memoryPrecision = 3,
    ): string {
        $spanLabel = ' span' . ($totalCount === 1 ? '' : 's');
        $countLabel = $filteredCount === $totalCount ? $spanLabel : " of {$totalCount}{$spanLabel}";

        $items = [
            SummaryChip::render((string) $filteredCount, $countLabel),
            SummaryChip::separator(),
            SummaryChip::render(Format::milliseconds($snapshot->time), ' total'),
            SummaryChip::separator(),
            SummaryChip::render(Format::bytesToMb($snapshot->memory, $memoryPrecision), ' peak'),
        ];

        if ($pageSizeSelector !== null) {
            $items[] = $pageSizeSelector;
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$items)
            ->render();
    }

    /**
     * @param list<ProfileRow> $rows
     */
    private static function renderTimeline(
        ProfilingSnapshot $profiling,
        array $rows,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string {
        $start = $summary->time * 1000;
        $duration = $profiling->time * 1000;

        if ($start <= 0.0 || $duration <= 0.0 || $profiling->memory <= 0) {
            return self::renderTimelineUnavailable();
        }

        $spans = TimelineGeometry::spans($rows, $start, $duration);

        $memorySvg = TimelineMemoryRenderer::render(
            self::timelineMemorySamples($profiling, $context),
            $start,
            $duration,
            $profiling->memory,
        );

        return str_replace(
            '<wbr>',
            '',
            TimelineRenderer::renderChart(
                $spans,
                TimelineGeometry::rulers($duration, 4),
                $memorySvg,
                $profiling->memory,
            ),
        );
    }

    private static function renderTimelineUnavailable(): string
    {
        return EmptyState::card(
            ProfileMessage::TIMELINE_UNAVAILABLE_HEADLINE->value,
            P::tag()->content(ProfileMessage::TIMELINE_UNAVAILABLE_EXPLANATION),
            P::tag()->content(ProfileMessage::TIMELINE_UNAVAILABLE_DETAILS),
        );
    }

    private static function renderUnifiedView(
        ProfilingSnapshot $profiling,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string {
        $entries = $profiling->entries();

        $search = ProfileSearch::fromQueryParams($context->queryParams);

        $queryParams = FilterRemoval::withGroup(
            $context->queryParams,
            FilterPrefix::PROFILE,
            $search->activeFilters,
            ['view', FilterPrefix::TIMELINE],
        );

        $filteredRows = $search->filter($entries);

        $content = self::renderSummary(
            count($filteredRows),
            count($entries),
            $profiling,
            null,
            2,
        );

        if ($entries === []) {
            return $content . self::renderEmptyState();
        }

        $content .= self::renderFilterForm($context, $search)
            . FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::PROFILE);

        if ($filteredRows === []) {
            return $content . self::renderNoMatchState();
        }

        $content .= H2::tag()->content(PanelTitle::TIMELINE)->render()
            . self::renderTimeline($profiling, $filteredRows, $context, $summary)
            . Header::tag()
                ->class('yii-debug-section-header')
                ->html(
                    H2::tag()->content('Details'),
                    PageSize::selectorFor($queryParams),
                )
                ->render();

        return $content . self::renderPaginatedGrid(
            $filteredRows,
            $entries,
            $context,
            $search->activeFilters,
            renderFilters: false,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): ProfilingSnapshot
    {
        return ProfilingSnapshot::fromArray($payload, '$.panels.profiling');
    }

    /**
     * @param list<ProfileRow> $rows
     *
     * @return list<ProfileRow>
     */
    private static function sortRows(array $rows, string|null $sort): array
    {
        $state = SortState::fromQuery($sort, self::SORT_ATTRIBUTES, 'duration', 'desc');

        return $state->apply(
            $rows,
            static fn(ProfileRow $left, ProfileRow $right): int => match ($state->attribute) {
                'seq' => $left->seq <=> $right->seq,
                'duration' => $left->duration <=> $right->duration,
                'category' => strcasecmp($left->category, $right->category),
                default => strcasecmp($left->info, $right->info),
            },
            static fn(ProfileRow $left, ProfileRow $right): int => $left->seq <=> $right->seq,
        );
    }

    /**
     * @return list<MemorySample>
     */
    private static function timelineMemorySamples(
        ProfilingSnapshot $profiling,
        PanelRenderContext $context,
    ): array {
        $samples = $profiling->samples();
        $logPayload = $context->panelPayload('log');

        if ($logPayload === null) {
            return $samples;
        }

        try {
            $entries = LogSnapshot::fromArray($logPayload, '$.panels.log')->entries();
        } catch (HydrationException) {
            return $samples;
        }

        foreach ($entries as $entry) {
            $samples[] = new MemorySample($entry->time, $entry->memory);
        }

        return $samples;
    }
}
