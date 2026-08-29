<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Helper\{Format, Icon, Vocabulary};
use PHPForge\Debug\Panel\Config\ConfigCardRenderer;
use PHPForge\Debug\PhpInfo\{PhpInfoDataNormalizer, PhpInfoRenderer};
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\Sidebar\{SidebarNavItem, SidebarRenderer, SidebarSnapshot, SidebarView};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H1;
use Yii3\Debug\Comparison\HistoryComparison;
use Yii3\Debug\ConfigDataFactory;
use Yiisoft\Assets\AssetManager;
use Yiisoft\View\WebView;

use function array_key_first;
use function array_keys;
use function array_search;
use function count;
use function date;
use function dirname;
use function is_string;
use function memory_get_peak_usage;
use function parse_url;
use function rawurlencode;
use function rtrim;

use const PHP_VERSION;

/**
 * Renders the history, Configuration, and phpinfo pages with the shared Debug Core shell.
 */
final readonly class DebugPageRenderer
{
    private string $routePrefix;
    private string $viewPath;

    public function __construct(
        private WebView $view,
        private AssetManager $assetManager,
        private ConfigDataFactory $configDataFactory,
        string $viewPath,
        string $routePrefix = '/debug',
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
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
            $this->viewSidebar($target, $manifest),
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public function config(string $tag, string $theme, array $manifest = []): string
    {
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
            $this->viewSidebar($manifest[$tag] ?? null, $manifest),
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     * @param array<array-key, mixed> $queryParams
     */
    public function history(array $manifest, array $queryParams, string $theme): string
    {
        $newestTag = array_key_first($manifest);

        return $this->page(
            'Request history',
            HistoryGridRenderer::render($manifest, $queryParams, $this->routePrefix),
            $theme,
            $newestTag === null ? null : $this->viewUrl($newestTag),
            $this->historySidebar($manifest, $queryParams),
        );
    }

    /**
     * @param array<string, RequestSummary> $manifest
     */
    public function phpInfo(string $theme, array $manifest = []): string
    {
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
            $this->viewSidebar($summary, $manifest),
        );
    }

    public function withRoutePrefix(string $routePrefix): self
    {
        return new self(
            $this->view,
            $this->assetManager,
            $this->configDataFactory,
            $this->viewPath,
            $routePrefix,
        );
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
    private function historySidebar(array $manifest, array $queryParams): SidebarView
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
            navItems: [$this->historyNavItem(true)],
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
     * @param array<string, RequestSummary> $manifest
     */
    private function snapshot(
        RequestSummary $summary,
        array $manifest,
        string $title = 'Current request',
        bool $isCursor = false,
        string $cursorInitTag = '',
    ): SidebarSnapshot {
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
                $newestTag === null ? '' : $this->viewUrl($newestTag),
                $oldestTag === null ? '' : $this->viewUrl($oldestTag),
                $newerTag === null ? '' : $this->viewUrl($newerTag),
                $olderTag === null ? '' : $this->viewUrl($olderTag),
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
    private function viewSidebar(RequestSummary|null $summary, array $manifest): SidebarView
    {
        return new SidebarView(
            snapshot: $summary === null ? null : $this->snapshot($summary, $manifest),
            navItems: [$this->historyNavItem(false, $summary?->tag)],
        );
    }

    private function viewUrl(string $tag): string
    {
        return "{$this->routePrefix}/view?tag=" . rawurlencode($tag) . '&panel=config';
    }
}
