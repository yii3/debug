<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Closure;
use InvalidArgumentException;
use JsonException;
use PHPForge\Debug\Data\{FilterPrefix, PageSize, QueryInput};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueCardRenderer};
use PHPForge\Debug\PhpInfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryScale, HistorySummary};
use PHPForge\Debug\View\Sidebar\{SidebarNavItem, SidebarRenderer, SidebarSnapshot, SidebarView};
use Throwable;
use UIAwesome\Html\Palpable\A;
use Yii3\Debug\Asset\Icon;
use Yii3\Debug\Grid\{PrefixedDropdownFilter, PrefixedTextFilter};
use Yii3\Debug\Panel\{ContextAwarePanelInterface, PanelGrid, PanelInterface};
use Yii3\Debug\Search\HistorySearch;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\View\WebView;
use Yiisoft\Yii\DataView\GridView\Column\{ColumnInterface, DataColumn, SerialColumn};

use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function date;
use function dirname;
use function http_build_query;
use function is_array;
use function json_encode;
use function max;
use function number_format;
use function parse_url;
use function rawurlencode;
use function rtrim;
use function str_replace;
use function trim;
use function ucwords;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Supplies Yii3 data and routes to the framework-neutral debugger templates.
 */
final readonly class DebugPageRenderer
{
    private const array SORT_ATTRIBUTES = [
        'method',
        'ip',
        'tag',
        'time',
        'statusCode',
        'sqlCount',
        'mailCount',
        'processingTime',
        'peakMemory',
    ];

    /**
     * @var array<string, PanelInterface> Presentation panels indexed by stable ID.
     */
    private array $panels;

    private string $routePrefix;
    private DebugUrlGenerator $urls;
    private string $viewPath;

    /**
     * @param WebView $view Yii3 PHP view renderer.
     * @param AssetManager $assetManager Yii3 asset manager.
     * @param Icon $icon Yii3 shared icon resolver.
     * @param PanelGrid $grid Shared debugger grid factory.
     * @param Aliases $aliases Yii3 path alias resolver.
     * @param string $viewPath Path or alias containing the shared debugger templates.
     * @param string $routePrefix URL prefix serving the Yii3 debugger pages.
     * @param iterable<PanelInterface> $panels Optional presentation panels.
     */
    public function __construct(
        private WebView $view,
        private AssetManager $assetManager,
        private Icon $icon,
        private PanelGrid $grid,
        Aliases $aliases,
        string $viewPath = '@yii3DebugViews',
        string $routePrefix = '/debug',
        iterable $panels = [],
    ) {
        $this->urls = new DebugUrlGenerator($routePrefix);
        $this->routePrefix = $this->urls->routePrefix();
        $this->viewPath = rtrim($aliases->get($viewPath), '/');
        $resolvedPanels = [];

        foreach ($panels as $panel) {
            $id = $panel->id();

            if (trim($id) === '') {
                throw new InvalidArgumentException('Yii3 debug panel ID must not be empty.');
            }

            if (isset($resolvedPanels[$id])) {
                throw new InvalidArgumentException("Duplicate Yii3 debug panel ID: {$id}.");
            }

            $resolvedPanels[$id] = $panel;
        }

        $this->panels = $resolvedPanels;
    }

    /**
     * Renders captured request history as a filterable, sortable, paginated grid.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->history($store->loadManifest(), $request->getQueryParams(), $theme);
     * ```
     *
     * @param array<string, RequestSummary> $summaries Captured requests ordered newest first.
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     * @param string $theme Resolved debugger theme (`'light'` or `'dark'`).
     *
     * @return string Complete HTML document.
     */
    public function history(array $summaries, array $queryParams = [], string $theme = 'light'): string
    {
        $search = HistorySearch::fromQueryParams($queryParams);
        $rows = array_map(HistoryRow::fromSummary(...), array_values($summaries));
        $filtered = $search->filter($rows);

        $summary = HistorySummary::fromManifest($summaries);
        $perPageRaw = QueryInput::scalar($queryParams, 'per-page');
        $pageSize = PageSize::resolve($perPageRaw);
        $effectiveSize = $pageSize ?? max(1, count($filtered));
        $scale = HistoryScale::fromModels($this->pageRows($filtered, $queryParams, $effectiveSize));

        $reader = (new IterableDataReader($filtered))->withSort(self::historySort());
        $paginator = (new OffsetPaginator($reader))->withPageSize($effectiveSize);

        $gridHtml = $this->grid
            ->full($this->routePrefix, $queryParams, FilterPrefix::DEBUG, 'yii-debug-history-filters')
            ->containerAttributes(['class' => 'yii-debug-grid yii-debug-grid-history'])
            ->bodyRowAttributes(
                static fn(array|object $row): array => $row instanceof HistoryRow
                    ? HistoryCellRenderer::buildRowAttributes($row, $search->isCodeCritical($row->statusCode))
                    : [],
            )
            ->dataReader($paginator)
            ->columns(...$this->historyColumns($summary, $scale))
            ->render();

        $bucketUrls = [];

        foreach ($summary->statusBuckets as $bucket) {
            $bucketUrls[$bucket->label] = $this->routePrefix
                . '?' . http_build_query(['Debug' => ['statusCode' => $bucket->sampleCode]]);
        }

        $content = '<h1 class="yii-debug-sr-only">Request history</h1>'
            . HistoryCellRenderer::renderSummary(
                $summary,
                $bucketUrls,
                PageSize::selectorHtml(PageSize::current($perPageRaw)),
            )
            . ActiveFilterBanner::render($search->activeFilters, $this->filterRemovalUrl($queryParams))
            . $gridHtml;

        $view = $this->view->withClearedState();
        $sidebar = SidebarRenderer::render(
            new SidebarView(
                snapshot: $this->indexSnapshotCard($summaries, $queryParams),
                navItems: $this->indexNavItems($summaries),
            ),
        );
        $newestTag = array_key_first($summaries);

        return $this->renderPage(
            $view,
            'Request history',
            $content,
            $sidebar,
            $theme,
            null,
            $this->icon->render('config'),
            'Config',
            'Open configuration',
            $newestTag === null ? null : $this->viewUrl($newestTag, 'config'),
        );
    }

    /**
     * Renders the standalone phpinfo report with the shared debugger shell.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->phpInfo($theme);
     * ```
     *
     * @param string $theme Resolved debugger theme (`'light'` or `'dark'`).
     *
     * @return string Complete HTML document.
     */
    public function phpInfo(string $theme = 'light'): string
    {
        $view = $this->view->withClearedState();
        $content = '<div class="yii-debug-page"><h1 class="yii-debug-hero-title">phpinfo</h1>'
            . PhpInfoRenderer::render(PhpInfoDataNormalizer::capture())
            . '</div>';
        $sidebar = SidebarRenderer::render(
            new SidebarView(
                snapshot: null,
                navItems: [
                    new SidebarNavItem('History', $this->icon->render('history'), $this->routePrefix, 'View request history', false),
                    new SidebarNavItem('PHP Info', $this->icon->render('php-alt'), $this->routePrefix . '/php-info', 'PHP runtime report', true),
                ],
            ),
        );

        return $this->renderPage($view, 'PHP Info', $content, $sidebar, $theme, null, null, 'History', 'View request history', $this->routePrefix);
    }

    /**
     * Renders one queue record inside the standard debugger shell.
     *
     * @param array<string, RequestSummary> $manifest Captured requests ordered newest first.
     */
    public function queueJob(
        DebugSnapshot $snapshot,
        JobRecord $record,
        array $manifest = [],
        string $theme = 'light',
    ): string {
        $summary = $snapshot->summary;
        $view = $this->view->withClearedState();
        $backUrl = $this->viewUrl($summary->tag, 'queue');
        $content = '<div class="yii-debug-queue-job-page"><header class="yii-debug-queue-job-head">'
            . A::tag()
                ->class('yii-debug-btn yii-debug-btn-ghost')
                ->content('← Back to grid')
                ->href($backUrl)
                ->render()
            . '</header>' . QueueCardRenderer::renderItem($record)->render() . '</div>';
        $sidebar = SidebarRenderer::render(
            new SidebarView(
                snapshot: $this->viewSnapshotCard($summary, $manifest, 'queue'),
                navItems: $this->viewNavItems($snapshot, 'queue'),
            ),
        );
        $configUrl = array_key_exists('config', $snapshot->panels)
            ? $this->viewUrl($summary->tag, 'config')
            : null;

        return $this->renderPage(
            $view,
            'Queue job',
            $content,
            $sidebar,
            $theme,
            self::memory($summary->peakMemory),
            $this->icon->render('config'),
            'Config',
            'Open configuration',
            $configUrl,
        );
    }

    /**
     * Renders one captured snapshot with adapter-defined panel metadata.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->snapshot($snapshot, 'request', $manifest, $request->getQueryParams(), $theme);
     * ```
     *
     * @param DebugSnapshot $snapshot Captured request snapshot.
     * @param string|null $selectedPanel Selected panel ID or `null` for the default panel.
     * @param array<string, RequestSummary> $manifest Captured requests ordered newest first.
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     * @param string $theme Resolved debugger theme (`'light'` or `'dark'`).
     *
     * @return string Complete HTML document.
     *
     * @throws JsonException When a stored payload cannot be encoded.
     */
    public function snapshot(
        DebugSnapshot $snapshot,
        string|null $selectedPanel = null,
        array $manifest = [],
        array $queryParams = [],
        string $theme = 'light',
    ): string {
        $panel = $this->resolveSelectedPanel($snapshot, $selectedPanel);
        $payload = $panel === 'summary'
            ? $snapshot->summary->jsonSerialize()
            : ($snapshot->panels[$panel] ?? []);
        $summary = $snapshot->summary;
        $view = $this->view->withClearedState();
        $failure = $snapshot->failures[$panel] ?? null;
        $panelContent = null;
        $renderError = null;

        if ($panel !== 'summary' && isset($this->panels[$panel]) && array_key_exists($panel, $snapshot->panels)) {
            try {
                $renderer = $this->panels[$panel];

                $panelContent = $renderer instanceof ContextAwarePanelInterface
                    ? $renderer->renderWithContext(
                        $payload,
                        new PanelRenderContext(
                            $summary->tag,
                            $panel,
                            $queryParams,
                            $theme,
                            $this->urls,
                            $snapshot->panels,
                        ),
                    )
                    : $renderer->render($payload);
            } catch (Throwable $throwable) {
                $renderError = $throwable->getMessage();
            }
        }

        $content = $this->render(
            $view,
            'snapshot.php',
            [
                'failure' => $failure === null
                    ? null
                    : ['exception' => (string) $failure->exception, 'stage' => $failure->stage],
                'method' => $summary->method,
                'panelContent' => $panelContent,
                'panelLabel' => $this->panelLabel($panel),
                'payload' => json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
                'renderError' => $renderError,
                'url' => $summary->url,
            ],
        );

        $configUrl = array_key_exists('config', $snapshot->panels)
            ? $this->viewUrl($summary->tag, 'config')
            : null;

        $sidebar = SidebarRenderer::render(
            new SidebarView(
                snapshot: $this->viewSnapshotCard($summary, $manifest, $panel),
                navItems: $this->viewNavItems($snapshot, $panel),
            ),
        );

        return $this->renderPage(
            $view,
            $summary->method . ' ' . $summary->url,
            $content,
            $sidebar,
            $theme,
            self::memory($summary->peakMemory),
            $this->icon->render('config'),
            'Config',
            'Open configuration',
            $configUrl,
        );
    }

    /**
     * Builds a readable fallback label for an adapter-defined panel ID.
     *
     * @param string $panel Panel identifier.
     *
     * @return string Panel label.
     */
    private static function fallbackPanelLabel(string $panel): string
    {
        $label = ucwords(str_replace(['_', '.', '-'], ' ', trim($panel, '_')));

        return $label !== '' ? $label : 'Panel';
    }

    /**
     * Builds the removal-URL closure consumed by the active-filter banner.
     *
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     *
     * @return Closure(list<string>): string Removal-URL builder.
     */
    private function filterRemovalUrl(array $queryParams): Closure
    {
        return function (array $without) use ($queryParams): string {
            /** @var list<string> $without */
            $params = $queryParams;
            $group = is_array($params[FilterPrefix::DEBUG] ?? null) ? $params[FilterPrefix::DEBUG] : [];

            foreach ($without as $attribute) {
                unset($group[$attribute]);
            }

            if ($group === []) {
                unset($params[FilterPrefix::DEBUG]);
            } else {
                $params[FilterPrefix::DEBUG] = $group;
            }

            unset($params['page']);

            if ($params === []) {
                return $this->routePrefix;
            }

            return $this->routePrefix . '?' . http_build_query($params);
        };
    }

    /**
     * Builds the history grid columns in yii2-parity order.
     *
     * @param HistorySummary $summary Manifest aggregate feeding the status filter dropdown.
     * @param HistoryScale $scale Page maxima the duration/memory gauges scale against.
     *
     * @return list<ColumnInterface> Grid columns.
     */
    private function historyColumns(HistorySummary $summary, HistoryScale $scale): array
    {
        $columns = [new SerialColumn(header: '#')];

        $columns[] = new DataColumn(
            property: 'tag',
            header: 'ID',
            headerAttributes: ['class' => 'yii-debug-col-id'],
            bodyAttributes: ['class' => 'yii-debug-col-id'],
            content: fn(HistoryRow $row): string => HistoryCellRenderer::renderTagCell(
                $row,
                $this->viewUrl($row->tag, 'request'),
            ),
            encodeContent: false,
            filter: new PrefixedTextFilter(
                FilterPrefix::DEBUG,
                ['class' => 'yii-debug-input yii-debug-col-id-input'],
            ),
            filterEmpty: static fn(): bool => true,
        );
        $columns[] = new DataColumn(
            property: 'time',
            header: 'Time',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderTimeCell($row),
            encodeContent: false,
        );
        $columns[] = new DataColumn(
            property: 'processingTime',
            header: 'Duration',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderDurationCell(
                $row,
                $scale->maxProcessingTime,
            ),
            encodeContent: false,
        );
        $columns[] = new DataColumn(
            property: 'peakMemory',
            header: 'Memory',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderMemoryCell(
                $row,
                $scale->maxPeakMemory,
            ),
            encodeContent: false,
        );
        $columns[] = new DataColumn(
            property: 'ip',
            header: 'IP',
            headerAttributes: ['class' => 'yii-debug-col-ip'],
            bodyAttributes: ['class' => 'yii-debug-col-ip'],
            filter: new PrefixedTextFilter(FilterPrefix::DEBUG),
            filterEmpty: static fn(): bool => true,
        );

        if (isset($this->panels['db'])) {
            $columns[] = new DataColumn(
                property: 'sqlCount',
                header: 'Query',
                headerAttributes: ['class' => 'yii-debug-col-num'],
                bodyAttributes: ['class' => 'yii-debug-col-num'],
                content: fn(HistoryRow $row): string => HistoryCellRenderer::renderSqlCountCell(
                    $row,
                    $this->viewUrl($row->tag, 'db'),
                    false,
                    0,
                ),
                encodeContent: false,
                filter: new PrefixedTextFilter(FilterPrefix::DEBUG),
                filterEmpty: static fn(): bool => true,
            );
        }

        if (isset($this->panels['mail'])) {
            $columns[] = new DataColumn(
                property: 'mailCount',
                header: 'Mail',
                headerAttributes: ['class' => 'yii-debug-col-num yii-debug-col-mail'],
                bodyAttributes: ['class' => 'yii-debug-col-num yii-debug-col-mail'],
                filter: new PrefixedTextFilter(FilterPrefix::DEBUG),
                filterEmpty: static fn(): bool => true,
            );
        }

        $columns[] = new DataColumn(
            property: 'method',
            header: 'Method',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderMethodCell($row),
            encodeContent: false,
            filter: new PrefixedDropdownFilter(
                FilterPrefix::DEBUG,
                [
                    'get' => 'GET',
                    'post' => 'POST',
                    'delete' => 'DELETE',
                    'put' => 'PUT',
                    'head' => 'HEAD',
                    'command' => 'COMMAND',
                ],
            ),
            filterEmpty: static fn(): bool => true,
        );
        $columns[] = new DataColumn(
            property: 'ajax',
            header: 'Ajax',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderAjaxCell($row),
            filter: new PrefixedDropdownFilter(FilterPrefix::DEBUG, ['0' => 'No', '1' => 'Yes']),
            filterEmpty: static fn(): bool => true,
        );
        $columns[] = new DataColumn(
            property: 'url',
            header: 'URL',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderUrlCell($row),
            encodeContent: false,
            filter: new PrefixedTextFilter(FilterPrefix::DEBUG),
            filterEmpty: static fn(): bool => true,
        );
        $columns[] = new DataColumn(
            property: 'statusCode',
            header: 'Status',
            content: static fn(HistoryRow $row): string => HistoryCellRenderer::renderStatusCell($row),
            encodeContent: false,
            filter: $summary->statusCodeFilter === null
                ? new PrefixedTextFilter(FilterPrefix::DEBUG)
                : new PrefixedDropdownFilter(
                    FilterPrefix::DEBUG,
                    array_map(static fn(int|string $code): string => (string) $code, $summary->statusCodeFilter),
                ),
            filterEmpty: static fn(): bool => true,
        );

        return $columns;
    }

    /**
     * Returns the history sort configuration: sortable columns without a default order, so the manifest's
     * newest-first order is preserved until the user sorts explicitly.
     */
    private static function historySort(): Sort
    {
        return Sort::only(self::SORT_ATTRIBUTES)->withoutDefaultSorting();
    }

    /**
     * Builds the index sidebar navigation (History + every registered non-config panel on the newest request).
     *
     * @param array<string, RequestSummary> $summaries Captured requests ordered newest first.
     *
     * @return list<SidebarNavItem> Navigation entries.
     */
    private function indexNavItems(array $summaries): array
    {
        $newestTag = array_key_first($summaries);
        $items = [
            new SidebarNavItem('History', $this->icon->render('history'), $this->routePrefix, 'View request history', true),
        ];

        foreach ($this->panels as $id => $panel) {
            if ($id === 'config') {
                continue;
            }

            $items[] = new SidebarNavItem(
                $panel->name(),
                $this->icon->render($panel->icon() ?? 'dump'),
                $newestTag === null ? '' : $this->viewUrl($newestTag, $id),
                $newestTag === null ? 'Pick a request first' : 'Open this panel on the newest request',
                false,
            );
        }

        return $items;
    }

    /**
     * Builds the cursor-mode snapshot card for the index sidebar, or `null` when the manifest is empty.
     *
     * @param array<string, RequestSummary> $summaries Captured requests ordered newest first.
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     */
    private function indexSnapshotCard(array $summaries, array $queryParams): SidebarSnapshot|null
    {
        $newestTag = array_key_first($summaries);

        if ($newestTag === null) {
            return null;
        }

        $newest = $summaries[$newestTag];

        return new SidebarSnapshot(
            title: 'Newest request',
            ariaLabel: 'Newest captured request',
            method: $newest->method,
            path: self::pathOnly($newest->url),
            fullUrl: $newest->url,
            statusCode: $newest->statusCode,
            statusVariant: self::statusVariant($newest->statusCode),
            time: $newest->time > 0 ? date('H:i:s', (int) $newest->time) : '',
            isAjax: $newest->ajax,
            isCursor: true,
            cursorInitTag: QueryInput::scalar($queryParams, 'cursor') ?? '',
            newestUrl: '',
            oldestUrl: '',
            newerUrl: '',
            olderUrl: '',
            isNewest: false,
            isOldest: false,
            hasNewer: true,
            hasOlder: true,
        );
    }

    /**
     * Formats peak memory usage.
     *
     * @param int|null $bytes Peak memory in bytes or `null` when unavailable.
     *
     * @return string Formatted memory usage.
     */
    private static function memory(int|null $bytes): string
    {
        return $bytes === null ? 'n/a' : number_format($bytes / 1_048_576, 1) . ' MiB';
    }

    /**
     * Computes the rows visible on the current page, mirroring the grid's own sort and slice.
     *
     * @param list<HistoryRow> $filtered Filtered rows.
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     * @param int $pageSize Effective page size.
     *
     * @return list<HistoryRow> Rows on the current page.
     */
    private function pageRows(array $filtered, array $queryParams, int $pageSize): array
    {
        $sort = self::historySort();
        $sortParam = QueryInput::scalar($queryParams, 'sort');

        if ($sortParam !== null) {
            $sort = $sort->withOrderString($sortParam);
        }

        /** @var list<HistoryRow> $sorted */
        $sorted = [...(new IterableDataReader($filtered))->withSort($sort)->read()];

        $page = max(1, (int) (QueryInput::scalar($queryParams, 'page') ?? '1'));

        return array_slice($sorted, ($page - 1) * $pageSize, $pageSize);
    }

    /**
     * Returns the adapter-defined label for a Yii3 panel.
     *
     * @param string $panel Panel identifier.
     *
     * @return string Panel label.
     */
    private function panelLabel(string $panel): string
    {
        return $panel === 'summary'
            ? 'Request'
            : (($this->panels[$panel] ?? null)?->name() ?? self::fallbackPanelLabel($panel));
    }

    /**
     * Returns the path-and-query display form of a captured URL.
     *
     * @param string $url Full captured URL.
     */
    private static function pathOnly(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $path = $parts['path'] ?? '/';

        return isset($parts['query']) ? "{$path}?{$parts['query']}" : $path;
    }

    /**
     * Renders a shared PHP template with the Yii3 view component.
     *
     * @param WebView $view Isolated Yii3 view instance.
     * @param string $template Shared template file name.
     * @param array<string, mixed> $parameters Template data.
     *
     * @return string Rendered template.
     */
    private function render(WebView $view, string $template, array $parameters): string
    {
        return $view->render($this->viewPath . '/' . $template, $parameters);
    }

    /**
     * Renders the common shell through the Yii3-specific asset layout.
     *
     * @param WebView $view Isolated Yii3 view instance.
     * @param string $title Page title.
     * @param string $content Rendered page content.
     * @param string $sidebar Pre-rendered sidebar markup.
     * @param string $theme Resolved debugger theme (`'light'` or `'dark'`).
     * @param string|null $peakMemory Formatted peak memory or `null` when unavailable.
     *
     * @return string Complete HTML document.
     */
    private function renderPage(
        WebView $view,
        string $title,
        string $content,
        string $sidebar,
        string $theme,
        string|null $peakMemory = null,
        string|null $actionIcon = null,
        string $actionLabel = 'History',
        string $actionTitle = 'View request history',
        string|null $actionUrl = null,
    ): string {
        $shell = $this->render(
            $view,
            '_shell.php',
            [
                'actionIcon' => $actionIcon ?? $this->icon->render('history'),
                'actionLabel' => $actionLabel,
                'actionTitle' => $actionTitle,
                'actionUrl' => $actionUrl,
                'content' => $content,
                'debugTheme' => $theme,
                'historyUrl' => $this->routePrefix,
                'mode' => 'view',
                'peakMemory' => $peakMemory,
                'phpIcon' => $this->icon->render('php-alt'),
                'phpVersion' => PHP_VERSION,
                'sidebar' => $sidebar,
                'themeIconMoon' => $this->icon->render('moon'),
                'themeIconSun' => $this->icon->render('sun'),
                'useShell' => true,
                'yiiIcon' => $this->icon->render('yii'),
                'yiiVersion' => '3',
            ],
        );

        return $view->render(
            dirname(__DIR__, 2) . '/resources/views/layout.php',
            [
                'assetManager' => $this->assetManager,
                'content' => $shell,
                'theme' => $theme,
                'title' => $title . ' — Yii Debugger',
            ],
        );
    }

    /**
     * Resolves the effective panel for a snapshot request, defaulting to the request panel when present.
     */
    private function resolveSelectedPanel(DebugSnapshot $snapshot, string|null $selectedPanel): string
    {
        if (
            $selectedPanel !== null
            && (
                $selectedPanel === 'summary'
                || array_key_exists($selectedPanel, $snapshot->panels)
                || array_key_exists($selectedPanel, $snapshot->failures)
            )
        ) {
            return $selectedPanel;
        }

        return array_key_exists('request', $snapshot->panels) ? 'request' : 'summary';
    }

    /**
     * Returns a semantic status class suffix.
     *
     * @param int $statusCode HTTP response status code.
     *
     * @return string Semantic status suffix.
     */
    private static function statusVariant(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => '5xx',
            $statusCode >= 400 => '4xx',
            $statusCode >= 300 => '3xx',
            $statusCode >= 200 => '2xx',
            default => 'none',
        };
    }

    /**
     * Builds the view sidebar navigation from the registered panels (DI order), hiding panels without content.
     *
     * @param DebugSnapshot $snapshot Captured request snapshot.
     * @param string $activePanel Currently selected panel ID.
     *
     * @return list<SidebarNavItem> Navigation entries.
     */
    private function viewNavItems(DebugSnapshot $snapshot, string $activePanel): array
    {
        $tag = $snapshot->summary->tag;
        $items = [
            new SidebarNavItem(
                'History',
                $this->icon->render('history'),
                $this->routePrefix . '?cursor=' . rawurlencode($tag),
                'View request history',
                false,
            ),
        ];

        foreach ($this->panels as $id => $panel) {
            if ($id === 'config') {
                continue;
            }

            $payload = $snapshot->panels[$id] ?? null;
            $hasFailure = array_key_exists($id, $snapshot->failures);

            if ($hasFailure === false && ($payload === null || $panel->hasContent($payload) === false)) {
                continue;
            }

            $items[] = new SidebarNavItem(
                $panel->name(),
                $this->icon->render($panel->icon() ?? 'dump'),
                $this->viewUrl($tag, $id),
                $panel->name(),
                $activePanel === $id,
            );
        }

        return $items;
    }

    /**
     * Builds the navigation-mode snapshot card for the view sidebar.
     *
     * @param RequestSummary $summary Summary of the displayed snapshot.
     * @param array<string, RequestSummary> $manifest Captured requests ordered newest first.
     * @param string $activePanel Currently selected panel ID.
     */
    private function viewSnapshotCard(RequestSummary $summary, array $manifest, string $activePanel): SidebarSnapshot
    {
        $tags = array_keys($manifest);
        $count = count($tags);
        $index = array_search($summary->tag, $tags, true);
        $panel = $activePanel === 'summary' ? 'request' : $activePanel;

        $newestTag = $tags[0] ?? null;
        $oldestTag = $tags[$count - 1] ?? null;
        $newerTag = $index !== false && $index > 0 ? ($tags[$index - 1] ?? null) : null;
        $olderTag = $index !== false && $index < $count - 1 ? ($tags[$index + 1] ?? null) : null;

        return new SidebarSnapshot(
            title: 'Current request',
            ariaLabel: 'Current request',
            method: $summary->method,
            path: self::pathOnly($summary->url),
            fullUrl: $summary->url,
            statusCode: $summary->statusCode,
            statusVariant: self::statusVariant($summary->statusCode),
            time: $summary->time > 0 ? date('H:i:s', (int) $summary->time) : '',
            isAjax: $summary->ajax,
            isCursor: false,
            cursorInitTag: '',
            newestUrl: $newestTag === null ? '' : $this->viewUrl($newestTag, $panel),
            oldestUrl: $oldestTag === null ? '' : $this->viewUrl($oldestTag, $panel),
            newerUrl: $newerTag === null ? '' : $this->viewUrl($newerTag, $panel),
            olderUrl: $olderTag === null ? '' : $this->viewUrl($olderTag, $panel),
            isNewest: $index === 0 || $index === false,
            isOldest: $index === false || $index === $count - 1,
            hasNewer: $index !== false && $index > 0,
            hasOlder: $index !== false && $index < $count - 1,
        );
    }

    /**
     * Builds a snapshot panel URL.
     *
     * @param string $tag Captured request tag.
     * @param string $panel Panel identifier.
     *
     * @return string Snapshot panel URL.
     */
    private function viewUrl(string $tag, string $panel): string
    {
        return $this->urls->panel($tag, $panel);
    }
}
