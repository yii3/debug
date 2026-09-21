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
use UIAwesome\Html\Phrasing\{Code, Label};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Search\ProfileSearch;
use Yii3\Debug\Web\{
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
final readonly class ProfilingPanel implements ToolbarPanelProviderInterface, ToolbarTitleProviderInterface
{
    /**
     * Defines the sortable attributes for the profiling grid.
     */
    private const array SORT_ATTRIBUTES = ['seq', 'duration', 'category', 'info'];

    /**
     * Reports the panel as listable for every capture.
     *
     * The collector records timing for every request, so the panel stays reachable from the sidebar even when the
     * capture holds no span.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool Always `true`.
     */
    public function hasContent(array $payload): bool
    {
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
     * Renders the unified Timeline and profiling details with navigation, filters, sorting, and pagination.
     *
     * @param PanelRenderInput $input Payload, request context, and request summary of the page being rendered.
     *
     * @return string Rendered detail content.
     */
    public function render(PanelRenderInput $input): string
    {
        return $this->renderPanel($input->payload, $input->context, $input->summary);
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
     * @param float $maxDuration Longest span of the whole capture, scaling the duration gauge.
     * @param SortState $state Sort state of the visible page.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return list<GridColumn<ProfileRow>> Columns in display order.
     */
    private static function columns(
        float $maxDuration,
        SortState $state,
        PanelRenderContext $context,
        array $queryParams,
    ): array {
        unset($queryParams['page']);

        $url = SortState::panelUrl($context, $queryParams);

        return [
            new GridColumn(
                header: $state->header('seq', 'Time', $url),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderTimeCell($row),
                bodyClass: 'yii-debug-cell-mono yii-debug-nowrap',
            ),
            new GridColumn(
                header: $state->header('duration', 'Duration', $url),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderDurationCell(
                    $row,
                    $maxDuration,
                ),
            ),
            new GridColumn(
                header: $state->header('category', 'Category', $url),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderCategoryCell($row),
                bodyClass: 'yii-debug-cell-mono yii-debug-cell-fqcn',
            ),
            new GridColumn(
                header: $state->header('info', ProfileMessage::INFO->value, $url),
                content: static fn(ProfileRow $row): string => ProfileCellRenderer::renderInfoCell($row),
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
     * @param float $maxDuration Longest span of the whole capture, scaling the duration gauge.
     * @param SortState $state Sort state of the visible page.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string Rendered grid.
     */
    private static function renderGrid(
        OffsetPaginator $paginator,
        float $maxDuration,
        SortState $state,
        PanelRenderContext $context,
        array $filters,
    ): string {
        $queryParams = FilterRemoval::withGroup(
            $context->queryParams,
            FilterPrefix::PROFILE,
            $filters,
            ['view', FilterPrefix::TIMELINE],
        );

        /** @var GridView<ProfileRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-profile-filters')
            ->columns(...self::columns($maxDuration, $state, $context, $queryParams))
            ->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));

        $footer = GridFooter::renderForPanel($paginator, $context, $queryParams);

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
     *
     * @return string Rendered grid.
     */
    private static function renderPaginatedGrid(
        array $filteredRows,
        array $entries,
        PanelRenderContext $context,
        array $filters = [],
    ): string {
        $queryParams = FilterRemoval::withGroup(
            $context->queryParams,
            FilterPrefix::PROFILE,
            $filters,
            ['view', FilterPrefix::TIMELINE],
        );

        $state = SortState::fromQuery(
            QueryInput::scalar($queryParams, 'sort'),
            self::SORT_ATTRIBUTES,
            'duration',
            'desc',
        );

        $sortedRows = self::sortRows($filteredRows, $state);

        $paginator = PageWindow::paginate(
            $sortedRows,
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        return self::renderGrid($paginator, ProfileRow::maxDuration($entries), $state, $context, $filters);
    }

    /**
     * Renders the Profiling panel as the unified Timeline and span-details view.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param RequestSummary $summary Summary of the capture being rendered.
     *
     * @return string Rendered detail content.
     */
    private function renderPanel(
        array $payload,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string {
        return PanelHeading::render(PanelTitle::PROFILING_DETAILS)
            . self::renderUnifiedView(self::snapshot($payload), $context, $summary);
    }

    /**
     * Renders the grid heading with the span totals, request metrics, and page-size selector.
     *
     * @param int $filteredCount Spans matching the active filters.
     * @param int $totalCount Spans captured in total.
     * @param ProfilingSnapshot $snapshot Typed snapshot of the capture.
     * @param int $memoryPrecision Decimal places used for the memory readout.
     *
     * @return string Rendered heading.
     */
    private static function renderSummary(
        int $filteredCount,
        int $totalCount,
        ProfilingSnapshot $snapshot,
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
     * @param SortState $state Sort state of the visible page.
     *
     * @return list<ProfileRow> Spans in display order.
     */
    private static function sortRows(array $rows, SortState $state): array
    {
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
