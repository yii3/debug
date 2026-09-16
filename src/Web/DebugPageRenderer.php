<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use InvalidArgumentException;
use PHPForge\Debug\Helper\{Format, Icon, Text, Vocabulary};
use PHPForge\Debug\Panel\Config\ConfigPanel;
use PHPForge\Debug\Panel\{PanelRenderContext, PanelRenderer};
use PHPForge\Debug\Panel\PanelTitle;
use PHPForge\Debug\PhpInfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPForge\Debug\View\Sidebar\{SidebarNavItem, SidebarRenderer, SidebarSnapshot, SidebarView};
use PHPForge\Debug\View\ViewMessage;
use Throwable;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H1;
use Yii3\Debug\Comparison\HistoryComparison;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Panel\{
    BuiltInPanels,
    ContextAndSummaryAwarePanelInterface,
    ContextAwarePanelInterface,
    DbPanel,
    ExtensionPanelInterface,
    ProviderPanel,
    SummaryAwarePanelInterface,
};
use Yii3\Debug\View\ViewMessage as AdapterMessage;
use Yiisoft\Assets\AssetManager;
use Yiisoft\View\WebView;

use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_search;
use function count;
use function date;
use function dirname;
use function is_string;
use function json_encode;
use function rawurlencode;
use function rtrim;
use function strcasecmp;
use function trim;
use function usort;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_VERSION;

/**
 * Renders debugger pages and optional extension panels with the shared Debug Core shell.
 */
final class DebugPageRenderer
{
    /**
     * Extension panels registered with the debugger.
     *
     * @var array<string, ExtensionPanelInterface>
     */
    private array $extensionPanels = [];
    /**
     * Indicates whether the renderer has been prepared.
     */
    private bool $prepared = false;
    /**
     * Base route used to generate debugger URLs.
     */
    private string $routePrefix = '/debug';
    /**
     * Path to the debugger view templates.
     */
    private readonly string $viewPath;

    /**
     * @param WebView $view View rendering the debugger templates.
     * @param AssetManager $assetManager Manager resolving the published URLs of the debugger assets.
     * @param ConfigDataFactory $configDataFactory Factory building the live configuration payload.
     * @param string $viewPath Directory the debugger templates are read from.
     */
    public function __construct(
        private readonly WebView $view,
        private readonly AssetManager $assetManager,
        private readonly ConfigDataFactory $configDataFactory,
        string $viewPath,
    ) {
        $this->viewPath = rtrim($viewPath, '/');
    }

    /**
     * Renders the comparison page for two captures.
     *
     * @param HistoryComparison $comparison Differences between the two captures.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param string $theme Resolved theme: `'light'` or `'dark'`.
     *
     * @return string Rendered debugger page.
     */
    public function compare(HistoryComparison $comparison, array $manifest, string $theme): string
    {
        $target = $comparison->target->summary;

        $panelLabels = ['config' => PanelTitle::CONFIGURATION->value];

        foreach ($this->extensionPanels as $id => $panel) {
            $panelLabels[$id] = $panel->name();
        }

        return $this->page(
            PanelTitle::COMPARE->value,
            HistoryComparisonRenderer::renderWithPanels(
                $comparison,
                $manifest,
                $this->routePrefix,
                $panelLabels,
            ),
            $theme,
            $this->viewUrl($target->tag),
            $this->viewSidebar($target, $manifest, $comparison->target),
            $target,
        );
    }

    /**
     * Renders the live configuration page.
     *
     * @param string $tag Capture in context, used by the sidebar links.
     * @param string $theme Resolved theme: `'light'` or `'dark'`.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     *
     * @return string Rendered debugger page.
     */
    public function config(
        string $tag,
        string $theme,
        array $manifest = [],
        DebugSnapshot|null $snapshot = null,
    ): string {
        $view = (new ConfigPanel())
            ->phpInfoUrl($this->routePrefix . '/php-info')
            ->present($this->configDataFactory->create());

        $content = Div::tag()
            ->class('yii-debug-page')
            ->html(PanelRenderer::render(PanelTitle::CONFIGURATION->value, $view))
            ->render();

        $configUrl = $this->viewUrl($tag);

        return $this->page(
            PanelTitle::CONFIGURATION->value,
            $content,
            $theme,
            $configUrl,
            $this->viewSidebar($snapshot->summary ?? $manifest[$tag] ?? null, $manifest, $snapshot),
            $snapshot->summary ?? $manifest[$tag] ?? null,
        );
    }

    /**
     * Renders one captured extension panel.
     *
     * @param DebugSnapshot $snapshot Capture the panel renders.
     * @param string $panelId Panel to render.
     * @param string $theme Resolved theme: `'light'` or `'dark'`.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return string Rendered debugger page.
     */
    public function extension(
        DebugSnapshot $snapshot,
        string $panelId,
        string $theme,
        array $manifest = [],
        array $queryParams = [],
    ): string {
        if (!$this->prepared) {
            return $this->forSnapshot($snapshot)->extension($snapshot, $panelId, $theme, $manifest, $queryParams);
        }

        $panel = $this->extensionPanels[$panelId] ?? null;

        if (
            $panel === null
            && !array_key_exists($panelId, $snapshot->panels)
            && !array_key_exists($panelId, $snapshot->failures)
        ) {
            throw new InvalidArgumentException(
                Message::EXTENSION_PANEL_UNKNOWN->getMessage($panelId),
            );
        }

        $payload = $snapshot->panels[$panelId] ?? [];
        $failure = $snapshot->failures[$panelId] ?? null;
        $panelContent = null;
        $renderError = null;

        if ($panel !== null && array_key_exists($panelId, $snapshot->panels)) {
            try {
                $context = new PanelRenderContext(
                    $snapshot->summary->tag,
                    $panelId,
                    $queryParams,
                    $theme,
                    new DebugUrlGenerator($this->routePrefix),
                    $snapshot->panels,
                );
                $panelContent = match (true) {
                    $panel instanceof ContextAndSummaryAwarePanelInterface => $panel->renderWithContextAndSummary(
                        $payload,
                        $context,
                        $snapshot->summary,
                    ),
                    $panel instanceof SummaryAwarePanelInterface => $panel->renderWithSummary(
                        $payload,
                        $snapshot->summary,
                    ),
                    $panel instanceof ContextAwarePanelInterface => $panel->renderWithContext(
                        $payload,
                        $context,
                    ),
                    default => $panel->render($payload),
                };
            } catch (Throwable $throwable) {
                $renderError = $throwable::class . ': ' . $throwable->getMessage();
            }
        }

        $content = $this->view->withClearedState()->render(
            $this->viewPath . '/snapshot.php',
            [
                'failure' => $failure === null
                    ? null
                    : [
                        'exception' => (string) $failure->exception,
                        'stage' => $failure->stage,
                    ],
                'method' => $snapshot->summary->method,
                'panelContent' => $panelContent,
                'panelLabel' => $panel?->name() ?? $panelId,
                'payload' => json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
                'renderError' => $renderError,
                'url' => Text::urlToPath($snapshot->summary->url),
            ],
        );

        return $this->page(
            $panel?->name() ?? $panelId,
            $content,
            $theme,
            $this->viewUrl($snapshot->summary->tag),
            $this->viewSidebar(
                $snapshot->summary,
                $manifest,
                $snapshot,
                $panelId,
            ),
            $snapshot->summary,
        );
    }

    /**
     * Returns whether a panel is registered under the given ID.
     *
     * @param string $panelId Panel to look up.
     *
     * @return bool `true` when the panel is registered; `false` otherwise.
     */
    public function hasExtensionPanel(string $panelId): bool
    {
        return isset($this->extensionPanels[$panelId]);
    }

    /**
     * Renders the captured request history.
     *
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param string $theme Resolved theme: `'light'` or `'dark'`.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     *
     * @return string Rendered debugger page.
     */
    public function history(
        array $manifest,
        array $queryParams,
        string $theme,
        DebugSnapshot|null $snapshot = null,
    ): string {
        $newestTag = array_key_first($manifest);

        $dbPanel = $this->extensionPanels['db'] ?? null;

        return $this->page(
            PanelTitle::REQUEST_HISTORY->value,
            HistoryGridRenderer::render(
                $manifest,
                $queryParams,
                $this->routePrefix,
                $dbPanel instanceof DbPanel ? $dbPanel : null,
            ),
            $theme,
            $newestTag === null ? null : $this->viewUrl($newestTag),
            $this->historySidebar($manifest, $queryParams, $snapshot),
            $newestTag === null ? null : $manifest[$newestTag],
        );
    }

    /**
     * Renders the `phpinfo()` page inside the debugger shell.
     *
     * @param string $theme Resolved theme: `'light'` or `'dark'`.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     *
     * @return string Rendered debugger page.
     */
    public function phpInfo(string $theme, array $manifest = [], DebugSnapshot|null $snapshot = null): string
    {
        $content = Div::tag()
            ->class('yii-debug-page')
            ->html(
                H1::tag()
                    ->class('yii-debug-hero-title')
                    ->content(PanelTitle::PHPINFO),
                PhpInfoRenderer::render(PhpInfoDataNormalizer::capture()),
            )
            ->render();

        $newestTag = array_key_first($manifest);

        $summary = $newestTag === null ? null : $manifest[$newestTag];

        return $this->page(
            PanelTitle::PHP_INFO->value,
            $content,
            $theme,
            $newestTag === null ? null : $this->viewUrl($newestTag),
            $this->viewSidebar($summary, $manifest, $snapshot),
            $summary,
        );
    }

    /**
     * Returns a copy carrying the extension panels shown in the sidebar.
     *
     * @param iterable<ExtensionPanelInterface> $extensionPanels Optional panel presenters in sidebar order.
     *
     * @return self Renderer with the panels applied.
     */
    public function withExtensionPanels(iterable $extensionPanels): self
    {
        $panels = [];

        foreach ($extensionPanels as $panel) {
            $id = trim($panel->id());

            if ($id === '') {
                throw new InvalidArgumentException(
                    Message::EXTENSION_PANEL_ID_EMPTY->getMessage(),
                );
            }

            if (isset($panels[$id])) {
                throw new InvalidArgumentException(
                    Message::EXTENSION_PANEL_ID_DUPLICATE->getMessage($id),
                );
            }

            $panels[$id] = $panel;
        }

        $new = clone $this;
        $new->extensionPanels = $panels;

        return $new;
    }

    /**
     * Returns a copy building every debugger URL on another base path.
     *
     * @param string $routePrefix Base path; a trailing slash is trimmed.
     *
     * @return self Renderer with the prefix applied.
     */
    public function withRoutePrefix(string $routePrefix): self
    {
        $new = clone $this;
        $new->routePrefix = rtrim($routePrefix, '/');

        return $new;
    }

    /**
     * Groups the extension panel links shown after the built-in navigation.
     *
     * @param RequestSummary|null $summary Summary of the capture in context, or `null` when none is selected.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     * @param string|null $activePanelId Panel to mark as active, or `null` when no panel is being shown.
     *
     * @return array<string, list<SidebarNavItem>> Navigation entries keyed by group label.
     */
    private function extensionNavGroups(
        RequestSummary|null $summary,
        DebugSnapshot|null $snapshot,
        string|null $activePanelId = null,
    ): array {
        if ($summary === null || $snapshot === null) {
            return [];
        }

        $items = [];

        foreach ($this->extensionPanels as $id => $panel) {
            if (BuiltInPanels::isBuiltIn($id)) {
                continue;
            }

            $hasFailure = isset($snapshot->failures[$id]);

            $payload = $snapshot->panels[$id] ?? null;
            $hasContent = false;

            if ($payload !== null) {
                try {
                    $hasContent = $panel->hasContent($payload);
                } catch (Throwable) {
                    // Keep malformed captured panels discoverable so the detail page can expose the render failure.
                    $hasContent = true;
                }
            }

            if ($hasFailure === false && $hasContent === false) {
                continue;
            }

            $items[] = new SidebarNavItem(
                label: $panel->name(),
                iconSvg: Icon::render($panel->icon()),
                url: $this->viewUrl($summary->tag, $id),
                tooltip: 'View ' . $panel->name() . ' panel',
                isActive: $activePanelId === $id,
            );
        }

        foreach (array_unique([...array_keys($snapshot->panels), ...array_keys($snapshot->failures)]) as $id) {
            if (isset($this->extensionPanels[$id])) {
                continue;
            }

            $items[] = new SidebarNavItem(
                label: $id,
                iconSvg: Icon::render('code'),
                url: $this->viewUrl($summary->tag, $id),
                tooltip: AdapterMessage::RAW_PANEL_TOOLTIP->value,
                isActive: $activePanelId === $id,
            );
        }

        usort(
            $items,
            static fn(SidebarNavItem $left, SidebarNavItem $right): int => strcasecmp($left->label, $right->label),
        );

        return $items === [] ? [] : [ViewMessage::EXTENSIONS->value => $items];
    }

    /**
     * Returns a copy whose extension panels are bound to one capture.
     *
     * @param DebugSnapshot $snapshot Capture the panels are bound to.
     *
     * @return self Renderer bound to that capture.
     */
    private function forSnapshot(DebugSnapshot $snapshot): self
    {
        $prepared = clone $this;
        $prepared->prepared = true;

        foreach ($this->extensionPanels as $id => $panel) {
            if ($panel instanceof ProviderPanel && isset($snapshot->panels[$id])) {
                $prepared->extensionPanels[$id] = $panel->forPayload($snapshot->panels[$id]);
            }
        }

        return $prepared;
    }

    /**
     * Builds the History entry of the sidebar navigation.
     *
     * @param bool $isActive Whether the History entry is the active one.
     * @param string|null $tag Capture the entry returns to, or `null` for the newest one.
     *
     * @return SidebarNavItem History navigation entry.
     */
    private function historyNavItem(bool $isActive, string|null $tag = null): SidebarNavItem
    {
        $url = $this->routePrefix;

        if ($tag !== null) {
            $url .= '?cursor=' . rawurlencode($tag);
        }

        return new SidebarNavItem(
            label: PanelTitle::HISTORY->value,
            iconSvg: Icon::render('history'),
            url: $url,
            tooltip: AdapterMessage::HISTORY_TOOLTIP->value,
            isActive: $isActive,
        );
    }

    /**
     * Builds the sidebar of the history page, where the navigator acts as a grid cursor.
     *
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     *
     * @return SidebarView Sidebar view-model for the history page.
     */
    private function historySidebar(array $manifest, array $queryParams, DebugSnapshot|null $snapshot): SidebarView
    {
        $newestTag = array_key_first($manifest);

        $summary = $newestTag === null ? null : $manifest[$newestTag];
        $cursor = $queryParams['cursor'] ?? null;

        return new SidebarView(
            snapshot: $summary === null
                ? null
                : $this->snapshot(
                    $summary,
                    $manifest,
                    title: ViewMessage::NEWEST_REQUEST->value,
                    isCursor: true,
                    cursorInitTag: is_string($cursor) ? $cursor : '',
                ),
            navItems: [
                $this->historyNavItem(true),
                ...$this->primaryPanelNavItems($summary, $snapshot),
            ],
            navGroups: $this->extensionNavGroups($summary, $snapshot),
        );
    }

    /**
     * Renders the debugger shell around already-composed page content.
     *
     * @param string $title Document title.
     * @param string $content Rendered page content.
     * @param string $theme Resolved theme: `'light'` or `'dark'`.
     * @param string|null $configUrl URL of the configuration page, or `null` when unavailable.
     * @param SidebarView $sidebar Sidebar view-model.
     * @param RequestSummary|null $summary Summary of the capture in context, or `null` when none is selected.
     *
     * @return string Rendered debugger page.
     */
    private function page(
        string $title,
        string $content,
        string $theme,
        string|null $configUrl,
        SidebarView $sidebar,
        RequestSummary|null $summary,
    ): string {
        $view = $this->view->withClearedState();
        $shell = $view->render(
            $this->viewPath . '/_shell.php',
            [
                'actionIcon' => Icon::render('config'),
                'actionLabel' => ViewMessage::CONFIG->value,
                'actionTitle' => 'Open configuration',
                'actionUrl' => $configUrl,
                'content' => $content,
                'debugTheme' => $theme,
                'historyUrl' => $this->routePrefix,
                'mode' => 'view',
                'peakMemory' => $summary?->peakMemory === null ? null : Format::bytesToMb($summary->peakMemory),
                'phpIcon' => Icon::render('php-alt'),
                'phpVersion' => PHP_VERSION,
                'sidebar' => SidebarRenderer::render($sidebar),
                'themeIconMoon' => Icon::render('moon'),
                'themeIconSun' => Icon::render('sun'),
                'useShell' => true,
                'yiiIcon' => Icon::render('yii'),
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
     * Builds the built-in panel navigation displayed after History and before extension groups.
     *
     * @param RequestSummary|null $summary Summary of the capture in context, or `null` when none is selected.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     * @param string|null $activePanelId Panel to mark as active, or `null` when no panel is being shown.
     *
     * @return list<SidebarNavItem> Navigation entries in display order.
     */
    private function primaryPanelNavItems(
        RequestSummary|null $summary,
        DebugSnapshot|null $snapshot,
        string|null $activePanelId = null,
    ): array {
        if ($summary === null || $snapshot === null) {
            return [];
        }

        $items = [];

        foreach (BuiltInPanels::IDS as $id) {
            $panel = $this->extensionPanels[$id] ?? null;

            if (
                $panel === null
                || (!array_key_exists($id, $snapshot->panels)
                    && !array_key_exists($id, $snapshot->failures))
            ) {
                continue;
            }

            $items[] = new SidebarNavItem(
                label: $panel->name(),
                iconSvg: Icon::render($panel->icon()),
                url: $this->viewUrl($summary->tag, $id),
                tooltip: 'View ' . $panel->name() . ' panel',
                isActive: $activePanelId === $id,
            );
        }

        return $items;
    }

    /**
     * Builds the snapshot card surfaced at the top of the sidebar.
     *
     * @param RequestSummary $summary Summary of the capture the card describes.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param string $title Card heading.
     * @param bool $isCursor Whether the navigator buttons act as a grid cursor.
     * @param string $cursorInitTag Capture the cursor lands on; empty to start at the newest one.
     * @param string $panelId Panel the navigator buttons keep open.
     *
     * @return SidebarSnapshot Snapshot card view-model.
     */
    private function snapshot(
        RequestSummary $summary,
        array $manifest,
        string $title = ViewMessage::CURRENT_REQUEST->value,
        bool $isCursor = false,
        string $cursorInitTag = '',
        string $panelId = 'config',
    ): SidebarSnapshot {
        $navigationPanelId = $panelId === 'request' ? 'auto' : $panelId;

        $tags = array_keys($manifest);
        $requestCount = count($tags);
        $index = array_search($summary->tag, $tags, true);

        $newestTag = $tags[0] ?? null;
        $oldestTag = $tags[$requestCount - 1] ?? null;
        $newerTag = $index !== false && $index > 0 ? ($tags[$index - 1] ?? null) : null;
        $olderTag = $index !== false && $index < $requestCount - 1 ? ($tags[$index + 1] ?? null) : null;

        return SidebarSnapshot::create($title)
            ->withRequest(
                $summary->method,
                Text::urlToPath($summary->url),
                $summary->url,
                $summary->time > 0 ? date('H:i:s', (int) $summary->time) : '',
                $summary->ajax,
            )
            ->withResponse(
                $summary->statusCode,
                Vocabulary::statusClass($summary->statusCode),
            )
            ->withCursor($isCursor, $cursorInitTag)
            ->withNavigationUrls(
                $newestTag === null ? '' : $this->viewUrl($newestTag, $navigationPanelId),
                $oldestTag === null ? '' : $this->viewUrl($oldestTag, $navigationPanelId),
                $newerTag === null ? '' : $this->viewUrl($newerTag, $navigationPanelId),
                $olderTag === null ? '' : $this->viewUrl($olderTag, $navigationPanelId),
            )
            ->withNavigationState(
                $index === 0 || $index === false,
                $index === false || $index === $requestCount - 1,
                $index !== false && $index > 0,
                $index !== false && $index < $requestCount - 1,
            );
    }

    /**
     * Builds the sidebar of a panel page, highlighting the active panel.
     *
     * @param RequestSummary|null $summary Summary of the capture in context, or `null` when none is selected.
     * @param array<string, RequestSummary> $manifest Captured request summaries keyed by tag.
     * @param DebugSnapshot|null $snapshot Capture backing the sidebar, or `null` when none is selected.
     * @param string|null $activePanelId Panel to mark as active, or `null` when no panel is being shown.
     *
     * @return SidebarView Sidebar view-model for the panel page.
     */
    private function viewSidebar(
        RequestSummary|null $summary,
        array $manifest,
        DebugSnapshot|null $snapshot = null,
        string|null $activePanelId = null,
    ): SidebarView {
        return new SidebarView(
            snapshot: $summary === null
                ? null
                : $this->snapshot(
                    $summary,
                    $manifest,
                    panelId: $activePanelId ?? 'config',
                ),
            navItems: [
                $this->historyNavItem(false, $summary?->tag),
                ...$this->primaryPanelNavItems(
                    $summary,
                    $snapshot,
                    $activePanelId,
                ),
            ],
            navGroups: $this->extensionNavGroups($summary, $snapshot, $activePanelId),
        );
    }

    /**
     * Builds the URL opening one panel of a capture.
     *
     * @param string $tag Capture to open.
     * @param string $panelId Panel to open within that capture.
     *
     * @return string URL of the panel view.
     */
    private function viewUrl(string $tag, string $panelId = 'config'): string
    {
        return "{$this->routePrefix}/view?tag=" . rawurlencode($tag) . '&panel=' . rawurlencode($panelId);
    }
}
