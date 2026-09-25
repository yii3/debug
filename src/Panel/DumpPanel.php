<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Helper\{EmptyState, Trace};
use PHPForge\Debug\Panel\Dump\{DumpCardRenderer, DumpRow, DumpSnapshot};
use PHPForge\Debug\Panel\Log\LogMessage;
use PHPForge\Debug\Panel\{PanelIcon, PanelRenderContext, PanelTitle};
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPForge\Debug\View\Grid\{FilterInput, PanelHeading, SortState, SummaryChip};
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Root\Header;
use Yii3\Debug\Search\LogSearch;
use Yii3\Debug\View\ViewMessage;
use Yii3\Debug\Web\{FilterRemoval, GridColumn, GridFooter, PageWindow, PanelGrid};
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\DataView\GridView\Column\Base\DataContext;
use Yiisoft\Yii\DataView\GridView\GridView;

use function count;
use function strcasecmp;

/**
 * Presents the values dumped during the request as the Yii2 Dump grid and contributes the dump count to the toolbar.
 *
 * The grid chrome is host-rendered like the Logs grid, while every dump card comes from
 * {@see DumpCardRenderer}, so a capture written by either host renders the same cells. Filters reuse the `Log[...]`
 * group of {@see LogSearch}, as the Yii2 Dump grid does.
 */
final readonly class DumpPanel implements ToolbarPanelProviderInterface
{
    /**
     * Attributes by which the dumps can be sorted in the grid view.
     */
    private const array SORT_ATTRIBUTES = [
        'time',
        'category',
        'message',
    ];

    /**
     * @param Trace $trace Configured renderer of the call-site frames shown in the dump cards.
     */
    public function __construct(private Trace $trace) {}

    /**
     * Reports whether the capture recorded a Dump payload worth opening the panel for.
     *
     * The capture is listed whenever it carries a payload, as in Yii2, so a request without dumps shows the empty
     * state.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return bool `true` when the capture carried data; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        return $payload !== [];
    }

    /**
     * Returns the icon key shared with the Yii2 Dump navigation entry.
     *
     * @return string Shared Debug Core icon key.
     */
    public function icon(): string
    {
        return PanelIcon::DUMP->value;
    }

    /**
     * Returns the identifier associating the panel with its slice of the captured payload.
     *
     * @return string Stable panel identifier.
     */
    public function id(): string
    {
        return 'dump';
    }

    /**
     * Returns the panel title shown in the debugger navigation.
     *
     * @return string Human-readable panel name.
     */
    public function name(): string
    {
        return PanelTitle::DUMP->value;
    }

    /**
     * Renders the detail view, letting the page context drive filtering, sorting, and paging.
     *
     * @param PanelRenderInput $input Payload, request context, and request summary of the page being rendered.
     *
     * @return string Rendered panel markup.
     */
    public function render(PanelRenderInput $input): string
    {
        $entries = self::snapshot($input->payload)->entries();

        $title = PanelHeading::render(PanelTitle::DUMP);

        if ($entries === []) {
            return $title . self::renderEmptyState();
        }

        $context = $input->context;

        $search = LogSearch::fromQueryParams($context->queryParams);

        $queryParams = FilterRemoval::withGroup($context->queryParams, FilterPrefix::LOG, $search->activeFilters);

        $content = $title . Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                SummaryChip::render((string) count($entries), ViewMessage::DUMP_CAPTURED_SUFFIX->value),
                PageSize::selectorFor($queryParams),
            )
            ->render();

        $content .= FilterRemoval::banner($search->activeFilters, $context, $queryParams, FilterPrefix::LOG);

        $filteredRows = $search->filter($entries);

        if ($filteredRows === []) {
            return $content . EmptyState::card(
                ViewMessage::DUMP_NO_MATCH_HEADLINE->value,
                P::tag()->content(ViewMessage::DUMP_NO_MATCH_EXPLANATION),
            );
        }

        return $content . $this->renderGrid($filteredRows, $context, $queryParams, $search->activeFilters);
    }

    /**
     * Builds the toolbar metric counting the dumped variables, as the Yii2 Dump chip does.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> The count metric, or an empty list when nothing was dumped.
     */
    public function toolbarItems(array $payload): array
    {
        $count = count(self::snapshot($payload)->entries());

        if ($count === 0) {
            return [];
        }

        return [
            ToolbarItem::create((string) $count)
                ->withStatus('info')
                ->withTitle(ViewMessage::DUMP_COUNT->value),
        ];
    }

    /**
     * Builds the grid columns of the Yii2 Dump grid: the category and the dump card.
     *
     * @param SortState $state Sort state of the visible page.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return list<GridColumn<DumpRow>> Columns in display order.
     */
    private function columns(
        SortState $state,
        PanelRenderContext $context,
        array $queryParams,
        array $filters,
    ): array {
        unset($queryParams['page']);

        $url = SortState::panelUrl($context, $queryParams);

        $traceLine = $this->trace->render(...);

        return [
            new GridColumn(
                header: $state->header('category', LogMessage::CATEGORY->value, $url),
                content: static fn(DumpRow $row): string => $row->category,
                filter: FilterInput::text(FilterPrefix::LOG, 'category', LogMessage::CATEGORY->value, $filters),
                encodeContent: true,
                bodyClass: 'yii-debug-cell-mono yii-debug-muted yii-debug-nowrap',
            ),
            new GridColumn(
                header: $state->header('message', LogMessage::MESSAGE->value, $url),
                content: static fn(DumpRow $row, DataContext $data): string => DumpCardRenderer::renderMessageCell(
                    $row,
                    $traceLine,
                    $data->index,
                ),
                filter: FilterInput::text(FilterPrefix::LOG, 'message', LogMessage::MESSAGE->value, $filters),
            ),
        ];
    }

    /**
     * Renders the card shown when the request dumped nothing.
     *
     * @return string Rendered empty-state card.
     */
    private static function renderEmptyState(): string
    {
        return EmptyState::card(
            ViewMessage::DUMP_EMPTY_HEADLINE->value,
            P::tag()->content(ViewMessage::DUMP_EMPTY_EXPLANATION),
            Pre::tag()
                ->class('yii-debug-empty-state-code')
                ->content(ViewMessage::DUMP_EMPTY_EXAMPLE),
        );
    }

    /**
     * Sorts and paginates the filtered dumps, then renders the grid of the visible page.
     *
     * @param list<DumpRow> $filteredRows Dumps matching the active filters.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request, filters normalized.
     * @param array<string, string> $filters Active filter values keyed by attribute.
     *
     * @return string Rendered grid.
     */
    private function renderGrid(
        array $filteredRows,
        PanelRenderContext $context,
        array $queryParams,
        array $filters,
    ): string {
        $state = SortState::fromQuery(QueryInput::scalar($queryParams, 'sort'), self::SORT_ATTRIBUTES, 'time');

        /** @var OffsetPaginator<int, DumpRow> $paginator */
        $paginator = PageWindow::paginate(
            self::sortRows($filteredRows, $state),
            QueryInput::scalar($queryParams, 'per-page'),
            QueryInput::scalar($queryParams, 'page'),
        );

        /** @var GridView<DumpRow> $grid */
        $grid = PanelGrid::filterable($paginator, 'yii-debug-dump-filters')
            ->columns(...$this->columns($state, $context, $queryParams, $filters))
            ->urlCreator(static fn(): string => $context->panelUrl(queryParams: []));

        return Div::tag()
            ->class('yii-debug-grid yii-debug-grid-dump')
            ->html($grid->render(), GridFooter::renderForPanel($paginator, $context, $queryParams))
            ->render();
    }

    /**
     * Decodes the captured payload into its typed snapshot.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return DumpSnapshot Typed snapshot decoded from the payload.
     */
    private static function snapshot(array $payload): DumpSnapshot
    {
        return DumpSnapshot::fromArray($payload, '$.panels.dump');
    }

    /**
     * Orders the dumps by the submitted sort expression, keeping capture order between equal rows.
     *
     * @param list<DumpRow> $rows Dumps to order.
     * @param SortState $state Sort state of the visible page.
     *
     * @return list<DumpRow> Dumps in display order.
     */
    private static function sortRows(array $rows, SortState $state): array
    {
        return $state->apply(
            $rows,
            static fn(DumpRow $left, DumpRow $right): int => match ($state->attribute) {
                'time' => $left->time <=> $right->time,
                'category' => strcasecmp($left->category, $right->category),
                default => strcasecmp($left->message, $right->message),
            },
        );
    }
}
