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
    PanelGrid,
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
    /**
     * Defines the sortable attributes for the profiling grid.
     */
    private const array SORT_ATTRIBUTES = ['seq', 'duration', 'category', 'info'];

    /**
     * Returns whether the capture holds activity worth listing in the sidebar.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the capture holds at least one span; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        self::snapshot($payload);

        return true;
    }

    /**
     * Returns the shared Debug Core icon key.
     *
     * @return string Icon key for the toolbar chip.
     */
    public function icon(): string
    {
        return PanelIcon::PROFILING->value;
    }

    /**
     * Returns the stable identifier of this panel.
     *
     * @return string Stable panel ID.
     */
    public function id(): string
    {
        return 'profiling';
    }

    /**
     * Returns the human-readable panel name.
     *
     * @return string Panel display name.
     */
    public function name(): string
    {
        return PanelTitle::PROFILING->value;
    }

    /**
     * Renders the profiling grid without debugger navigation.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return string Rendered detail content.
     */
    public function render(array $payload): string
    {
        return $this->renderPanel($payload);
    }

    /**
     * Renders the profiling grid with debugger navigation, filters, sorting, and pagination.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     *
     * @return string Rendered detail content.
     */
    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        return $this->renderPanel($payload, $context);
    }

    /**
     * Renders the unified Timeline and profiling details, which need the request geometry.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param RequestSummary $summary Summary of the capture being rendered.
     *
     * @return string Rendered detail content.
     */
    public function renderWithContextAndSummary(
        array $payload,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string {
        return $this->renderPanel($payload, $context, $summary);
    }

    /**
     * Builds the toolbar metrics shown for this panel.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> Processing time and peak memory of the capture.
     */
    public function toolbarItems(array $payload): array
    {
        $snapshot = self::snapshot($payload);

        return [
            ToolbarItem::create(Format::milliseconds($snapshot->time))
                ->withTitle(ProfileMessage::TOOLBAR_TIME->value),
            ToolbarItem::create(Format::bytesToMb($snapshot->memory, 3))
                ->withTitle(ProfileMessage::TOOLBAR_MEMORY->value),
        ];
    }

    /**
     * Returns the toolbar title, hidden so the gauge icon stands alone.
     *
     * @return string Empty string, keeping the gauge icon while hiding the text label.
     */
    public function toolbarTitle(): string
    {
        return '';
    }

    /**
     * Builds the grid columns for the captured spans.
     *
     * @param float $maxDuration Longest span on the visible page, scaling the duration gauge.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param bool $renderFilters Whether to emit the filter row.
     *
     * @return list<GridColumn<ProfileRow>> Columns in display order.
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
                header: self::header('info', ProfileMessage::INFO->value, $context, $queryParams),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderInfoCell($row),
                filter: $filterCells
                    ? FilterInput::text(FilterPrefix::PROFILE, 'info', ProfileMessage::INFO->value, $filters)
                    : null,
            ),
        ];
    }

    /**
     * Preserves the routing and display state when the filter form is submitted.
     *
     * @param PanelRenderContext $context State of the debugger request being rendered.
     *
     * @return array<string, string> Hidden form fields keyed by parameter name.
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
     * @param string $attribute Attribute the column sorts on.
     * @param string $label Visible column label.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return string Rendered header cell.
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

    /**
     * Renders the card shown when the capture holds no span.
     *
     * @return string Empty-state card shown when the capture holds no span.
     */
    private static function renderEmptyState(): string
    {
        return EmptyState::card(
            ProfileMessage::EMPTY_HEADLINE->value,
            P::tag()
                ->html(
                    ProfileMessage::EMPTY_PRODUCED->value,
                    Code::tag()->content('ProfilerInterface::begin()'),
                    ProfileMessage::EMPTY_SEPARATOR->value,
                    Code::tag()->content('ProfilerInterface::end()'),
                    ProfileMessage::EMPTY_NO_SPANS->value,
                ),
            P::tag()->content(ProfileMessage::EMPTY_CALL_TO_ACTION),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content(ProfileMessage::EMPTY_EXAMPLE),
            P::tag()->content(ProfileMessage::EMPTY_DB_NOTE),
        );
    }

    /**
     * Renders the filter form above the spans grid.
     *
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param ProfileSearch $search Search model holding the submitted filter values.
     *
     * @return string Rendered filter form.
     */
    private static function renderFilterForm(PanelRenderContext $context, ProfileSearch $search): string
    {
        $children = [];

        foreach (self::filterHiddenParams($context) as $name => $value) {
            $children[] = InputHidden::tag()
                ->name($name)
                ->value($value);
        }

        $children[] = Div::tag()
            ->class('yii-debug-tl-field')
            ->html(
                Label::tag()
                    ->content(ProfileMessage::MIN_DURATION)
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
                    ->content(ProfileMessage::CATEGORY)
                    ->for('profile-category'),
                InputText::tag()
                    ->id('profile-category')
                    ->name(FilterPrefix::PROFILE . '[category]')
                    ->placeholder(ProfileMessage::CATEGORY_PLACEHOLDER->value)
                    ->value($search->category()),
            );
        $children[] = Div::tag()
            ->class('yii-debug-tl-field yii-debug-tl-field-grow')
            ->html(
                Label::tag()
                    ->content(ProfileMessage::INFO)
                    ->for('profile-info'),
                InputText::tag()
                    ->id('profile-info')
                    ->name(FilterPrefix::PROFILE . '[info]')
                    ->placeholder(ProfileMessage::INFO_PLACEHOLDER->value)
                    ->value($search->info()),
            );
        $children[] = Button::tag()
            ->class('yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm')
            ->content(ProfileMessage::APPLY)
            ->type('submit');

        return Form::tag()
            ->action($context->panelUrl(queryParams: []))
            ->addAriaAttribute('label', ProfileMessage::FILTERS->value)
            ->class('yii-debug-tl-filter')
            ->html(...$children)
            ->method('get')
            ->render();
    }

    /**
     * Renders the spans grid for the visible page.
     *
     * @param OffsetPaginator<int, ProfileRow> $paginator Paginator clamped to the visible page.
     * @param float $maxDuration Longest span on the visible page, scaling the duration gauge.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param bool $renderFilters Whether to emit the filter row.
     *
     * @return string Rendered grid.
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
        $grid = PanelGrid::filterable($paginator, 'yii-debug-profile-filters')
            ->columns(...self::columns($maxDuration, $context, $queryParams, $filters, $renderFilters))
            ->urlCreator($context === null ? null : static fn(): string => $context->panelUrl(queryParams: []));

        $footer = GridFooter::renderForPanel(
            $paginator,
            $paginator->getCurrentPageSize(),
            $context,
            $queryParams,
        );

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-profile')
            ->html($grid->render(), $footer)
            ->render();
    }

    /**
     * Renders the card shown when no span matches the active filters.
     *
     * @return string Empty-state card shown when no span matches the active filters.
     */
    private static function renderNoMatchState(): string
    {
        return EmptyState::card(
            ProfileMessage::NO_MATCH_HEADLINE->value,
            P::tag()->content(ProfileMessage::NO_MATCH_EXPLANATION),
        );
    }

    /**
     * Paginates the filtered spans and renders the resulting page.
     *
     * @param list<ProfileRow> $filteredRows Spans matching the active filters.
     * @param list<ProfileRow> $entries Every captured span, scaling the duration gauge.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     * @param bool $renderFilters Whether to emit the filter row.
     *
     * @return string Rendered grid.
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

        return self::renderGrid($paginator, ProfileRow::maxDuration($entries), $context, $filters, $renderFilters);
    }

    /**
     * Renders the Profiling panel, switching to the unified Timeline view when a summary is available.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     * @param RequestSummary|null $summary Summary of the capture, or `null` when it is unavailable.
     *
     * @return string Rendered detail content.
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

    /**
     * Renders the profiling grid view for a capture.
     *
     * @param ProfilingSnapshot $snapshot Typed snapshot of the capture.
     * @param PanelRenderContext|null $context State of the debugger request, or `null` when the panel renders
     * standalone.
     *
     * @return string Rendered detail content.
     */
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
            return $content . self::renderGrid(PageWindow::single($filteredRows), ProfileRow::maxDuration($entries));
        }

        $filterBanner = FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::PROFILE);

        if ($filteredRows === []) {
            return $content . $filterBanner . self::renderNoMatchState();
        }

        return "{$content}{$filterBanner}"
            . self::renderPaginatedGrid($filteredRows, $entries, $context, $search->activeFilters);
    }

    /**
     * Renders the grid heading with the span totals, request metrics, and page-size selector.
     *
     * @param int $filteredCount Spans matching the active filters.
     * @param int $totalCount Spans captured in total.
     * @param ProfilingSnapshot $snapshot Typed snapshot of the capture.
     * @param string|null $pageSizeSelector Rendered page-size selector, or `null` to omit it.
     * @param int $memoryPrecision Decimal places used for the memory readout.
     *
     * @return string Rendered heading.
     */
    private static function renderSummary(
        int $filteredCount,
        int $totalCount,
        ProfilingSnapshot $snapshot,
        string|null $pageSizeSelector,
        int $memoryPrecision = 3,
    ): string {
        $spanLabel = $totalCount === 1
            ? ProfileMessage::SPAN_SUFFIX->value
            : ProfileMessage::SPANS_SUFFIX->value;
        $countLabel = $filteredCount === $totalCount ? $spanLabel : " of {$totalCount}{$spanLabel}";

        $items = [
            SummaryChip::render((string) $filteredCount, $countLabel),
            SummaryChip::separator(),
            SummaryChip::render(Format::milliseconds($snapshot->time), ProfileMessage::TOTAL_SUFFIX->value),
            SummaryChip::separator(),
            SummaryChip::render(
                Format::bytesToMb($snapshot->memory, $memoryPrecision),
                ProfileMessage::PEAK_SUFFIX->value,
            ),
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
     * Renders the Timeline chart for the captured spans.
     *
     * @param ProfilingSnapshot $profiling Typed snapshot of the capture.
     * @param list<ProfileRow> $rows Spans to plot, in capture order.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param RequestSummary $summary Summary of the capture being rendered.
     *
     * @return string Rendered Timeline.
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

    /**
     * Renders the card shown when the capture carries no usable Timeline geometry.
     *
     * @return string Empty-state card shown when the capture carries no usable Timeline geometry.
     */
    private static function renderTimelineUnavailable(): string
    {
        return EmptyState::card(
            ProfileMessage::TIMELINE_UNAVAILABLE_HEADLINE->value,
            P::tag()->content(ProfileMessage::TIMELINE_UNAVAILABLE_EXPLANATION),
            P::tag()->content(ProfileMessage::TIMELINE_UNAVAILABLE_DETAILS),
        );
    }

    /**
     * Renders the Timeline and the span-details grid as one view.
     *
     * @param ProfilingSnapshot $profiling Typed snapshot of the capture.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param RequestSummary $summary Summary of the capture being rendered.
     *
     * @return string Rendered detail content.
     */
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
                    H2::tag()->content(ProfileMessage::DETAILS),
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
     * Decodes the captured payload into its typed snapshot.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return ProfilingSnapshot Typed snapshot decoded from the payload.
     */
    private static function snapshot(array $payload): ProfilingSnapshot
    {
        return ProfilingSnapshot::fromArray($payload, '$.panels.profiling');
    }

    /**
     * Orders the captured spans by the submitted sort expression.
     *
     * @param list<ProfileRow> $rows Spans to order.
     * @param string|null $sort Submitted sort expression, or `null` to keep capture order.
     *
     * @return list<ProfileRow> Spans in display order.
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
     * Collects the memory samples plotted under the Timeline, merging the Logs panel readings when available.
     *
     * @param ProfilingSnapshot $profiling Typed snapshot of the capture.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     *
     * @return list<MemorySample> Samples in chronological order.
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
            $entries = LogSnapshot::fromArray(
                $logPayload,
                '$.panels.log',
            )->entries();
        } catch (HydrationException) {
            return $samples;
        }

        foreach ($entries as $entry) {
            $samples[] = new MemorySample($entry->time, $entry->memory);
        }

        return $samples;
    }
}
