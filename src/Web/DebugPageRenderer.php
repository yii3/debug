<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use InvalidArgumentException;
use PHPForge\Debug\Helper\{Format, Icon, Vocabulary};
use PHPForge\Debug\Panel\Config\ConfigCardRenderer;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\PhpInfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPForge\Debug\View\Sidebar\{SidebarNavItem, SidebarRenderer, SidebarSnapshot, SidebarView};
use Throwable;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H1;
use Yii3\Debug\Comparison\HistoryComparison;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Panel\{
    ContextAndSummaryAwarePanelInterface,
    ContextAwarePanelInterface,
    ExtensionPanelInterface,
    SummaryAwarePanelInterface,
};
use Yiisoft\Assets\AssetManager;
use Yiisoft\View\WebView;

use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_search;
use function count;
use function date;
use function dirname;
use function in_array;
use function is_string;
use function json_encode;
use function memory_get_peak_usage;
use function parse_url;
use function rawurlencode;
use function rtrim;
use function trim;

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
     * Built-in panels shown in the primary request navigation.
     */
    private const array PRIMARY_PANEL_IDS = [
        'request',
        'log',
        'profiling',
    ];

    /**
     * @var array<string, ExtensionPanelInterface>
     */
    private array $extensionPanels = [];
    private string $routePrefix = '/debug';
    private readonly string $viewPath;

    public function __construct(
        private readonly WebView $view,
        private readonly AssetManager $assetManager,
        private readonly ConfigDataFactory $configDataFactory,
        string $viewPath,
    ) {
        $this->viewPath = rtrim($viewPath, '/');
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public function compare(HistoryComparison $comparison, array $manifest, string $theme): string
    {
        $target = $comparison->target->summary;

        return $this->page(
            'Compare captures',
            HistoryComparisonRenderer::render($comparison, $manifest, $this->routePrefix),
            $theme,
            $this->viewUrl($target->tag),
            $this->viewSidebar($target, $manifest, $comparison->target),
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public function config(
        string $tag,
        string $theme,
        array $manifest = [],
        DebugSnapshot|null $snapshot = null,
    ): string {
        $summary = $this->configDataFactory->create();

        $content = Div::tag()
            ->class('yii-debug-page')
            ->html(
                H1::tag()
                    ->class('yii-debug-sr-only')
                    ->content('Configuration'),
                ConfigCardRenderer::renderReadoutGrid($summary),
                ConfigCardRenderer::renderPhpExtensionsSection($summary->php),
                ConfigCardRenderer::renderApplicationDetailsSection($summary->application),
            );
        $installedExtensions = ConfigCardRenderer::renderInstalledExtensionsSection($summary);

        if ($installedExtensions !== null) {
            $content = $content->html($installedExtensions);
        }

        $content = $content
            ->html(ConfigCardRenderer::renderPhpInfoCta($this->routePrefix . '/php-info'))
            ->render();

        $configUrl = $this->viewUrl($tag);

        return $this->page(
            'Configuration',
            $content,
            $theme,
            $configUrl,
            $this->viewSidebar($manifest[$tag] ?? null, $manifest, $snapshot),
        );
    }

    /**
     * Renders one captured extension panel.
     *
     * @param array<string, RequestSummary> $manifest
     * @param array<array-key, mixed> $queryParams
     */
    public function extension(
        DebugSnapshot $snapshot,
        string $panelId,
        string $theme,
        array $manifest = [],
        array $queryParams = [],
    ): string {
        $panel = $this->extensionPanels[$panelId] ?? null;

        if ($panel === null) {
            throw new InvalidArgumentException(
                "Unknown debug extension panel: {$panelId}.",
            );
        }

        $payload = $snapshot->panels[$panelId] ?? [];
        $failure = $snapshot->failures[$panelId] ?? null;
        $panelContent = null;
        $renderError = null;

        if (array_key_exists($panelId, $snapshot->panels)) {
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
                'panelLabel' => $panel->name(),
                'payload' => json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
                'renderError' => $renderError,
                'url' => self::path($snapshot->summary->url),
            ],
        );
        return $this->page(
            $panel->name(),
            $content,
            $theme,
            $this->viewUrl($snapshot->summary->tag),
            $this->viewSidebar(
                $snapshot->summary,
                $manifest,
                $snapshot,
                $panelId,
            ),
        );
    }

    public function hasExtensionPanel(string $panelId): bool
    {
        return isset($this->extensionPanels[$panelId]);
    }

    /**
     * @param array<string, RequestSummary> $manifest
     * @param array<array-key, mixed> $queryParams
     */
    public function history(
        array $manifest,
        array $queryParams,
        string $theme,
        DebugSnapshot|null $snapshot = null,
    ): string {
        $newestTag = array_key_first($manifest);

        return $this->page(
            'Request history',
            HistoryGridRenderer::render($manifest, $queryParams, $this->routePrefix),
            $theme,
            $newestTag === null ? null : $this->viewUrl($newestTag),
            $this->historySidebar($manifest, $queryParams, $snapshot),
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public function phpInfo(
        string $theme,
        array $manifest = [],
        DebugSnapshot|null $snapshot = null,
    ): string {
        $content = Div::tag()
            ->class('yii-debug-page')
            ->html(
                H1::tag()
                    ->class('yii-debug-hero-title')
                    ->content('phpinfo'),
                PhpInfoRenderer::render(PhpInfoDataNormalizer::capture()),
            )
            ->render();

        $newestTag = array_key_first($manifest);

        $summary = $newestTag === null ? null : $manifest[$newestTag];

        return $this->page(
            'PHP Info',
            $content,
            $theme,
            $newestTag === null ? null : $this->viewUrl($newestTag),
            $this->viewSidebar($summary, $manifest, $snapshot),
        );
    }

    /**
     * @param iterable<ExtensionPanelInterface> $extensionPanels Optional panel presenters in sidebar order.
     */
    public function withExtensionPanels(iterable $extensionPanels): self
    {
        $panels = [];

        foreach ($extensionPanels as $panel) {
            $id = trim($panel->id());

            if ($id === '') {
                throw new InvalidArgumentException(
                    'Debug extension panel ID must not be empty.',
                );
            }

            if (isset($panels[$id])) {
                throw new InvalidArgumentException(
                    "Duplicate debug extension panel ID: {$id}.",
                );
            }

            $panels[$id] = $panel;
        }

        $new = clone $this;
        $new->extensionPanels = $panels;

        return $new;
    }

    public function withRoutePrefix(string $routePrefix): self
    {
        $new = clone $this;
        $new->routePrefix = rtrim($routePrefix, '/');

        return $new;
    }

    /**
     * @return array<string, list<SidebarNavItem>>
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
            if (in_array($id, self::PRIMARY_PANEL_IDS, true)) {
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

        return $items === [] ? [] : ['Extensions' => $items];
    }

    private function historyNavItem(bool $isActive, string|null $tag = null): SidebarNavItem
    {
        $url = $this->routePrefix;

        if ($tag !== null) {
            $url .= '?cursor=' . rawurlencode($tag);
        }

        return new SidebarNavItem(
            label: 'History',
            iconSvg: Icon::render('history'),
            url: $url,
            tooltip: 'View request history',
            isActive: $isActive,
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     * @param array<array-key, mixed> $queryParams
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
                    title: 'Newest request',
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

    private function page(
        string $title,
        string $content,
        string $theme,
        string|null $configUrl,
        SidebarView $sidebar,
    ): string {
        $view = $this->view->withClearedState();
        $shell = $view->render(
            $this->viewPath . '/_shell.php',
            [
                'actionIcon' => Icon::render('config'),
                'actionLabel' => 'Config',
                'actionTitle' => 'Open configuration',
                'actionUrl' => $configUrl,
                'content' => $content,
                'debugTheme' => $theme,
                'historyUrl' => $this->routePrefix,
                'mode' => 'view',
                'peakMemory' => Format::bytesToMb(memory_get_peak_usage(true)),
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

    private static function path(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return $url;
        }

        $path = is_string($parsed['path'] ?? null) ? $parsed['path'] : '/';
        $query = is_string($parsed['query'] ?? null) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
        $fragment = is_string($parsed['fragment'] ?? null) && $parsed['fragment'] !== ''
            ? '#' . $parsed['fragment']
            : '';

        return $path . $query . $fragment;
    }

    /**
     * Builds the built-in panel navigation displayed after History and before extension groups.
     *
     * @return list<SidebarNavItem>
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

        foreach (self::PRIMARY_PANEL_IDS as $id) {
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
     * @param array<string, RequestSummary> $manifest
     */
    private function snapshot(
        RequestSummary $summary,
        array $manifest,
        string $title = 'Current request',
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
                self::path($summary->url),
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
     * @param array<string, RequestSummary> $manifest
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

    private function viewUrl(string $tag, string $panelId = 'config'): string
    {
        return "{$this->routePrefix}/view?tag=" . rawurlencode($tag) . '&panel=' . rawurlencode($panelId);
    }
}
