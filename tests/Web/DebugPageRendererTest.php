<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPForge\Vite\Vite;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Panel\{InertiaPanel, VitePanel};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function sys_get_temp_dir;

/**
 * Integration tests for the two Debug Core brand pages.
 */
final class DebugPageRendererTest extends TestCase
{
    public function testConfigOmitsExtensionsGroupForEmptyInertiaCapture(): void
    {
        $snapshot = new DebugSnapshot(
            $this->manifest()['request-1'],
            [
                'inertia' => InertiaSnapshot::capture(null, null, [], [], 200)->jsonSerialize(),
            ],
            [],
        );

        $html = $this->rendererWithInertia()->config(
            'request-1',
            'light',
            $this->manifest(),
            $snapshot,
        );

        self::assertStringNotContainsString(
            'yii-debug-nav-group',
            $html,
            'An empty Inertia capture must not create an Extensions navigation group.',
        );
    }

    public function testConfigShowsCapturedInertiaUnderExtensions(): void
    {
        $snapshot = new DebugSnapshot(
            $this->manifest()['request-1'],
            ['inertia' => $this->inertiaPayload()],
            [],
        );

        $html = $this->rendererWithInertia()->config(
            'request-1',
            'light',
            $this->manifest(),
            $snapshot,
        );

        self::assertMatchesRegularExpression(
            '/yii-debug-nav-group.*Extensions.*Inertia/s',
            $html,
            'Captured Inertia activity must appear in the Extensions navigation group.',
        );
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=inertia',
            $html,
            'Inertia navigation must link to its captured panel.',
        );
    }

    public function testConfigShowsCapturedViteUnderExtensions(): void
    {
        $snapshot = new DebugSnapshot(
            $this->manifest()['request-1'],
            ['vite' => $this->vitePayload()],
            [],
        );

        $html = $this->rendererWithVite()->config(
            'request-1',
            'light',
            $this->manifest(),
            $snapshot,
        );

        self::assertMatchesRegularExpression(
            '/yii-debug-nav-group.*Extensions.*Vite/s',
            $html,
            'Captured Vite configuration must appear in the Extensions navigation group.',
        );
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=vite',
            $html,
            'Vite navigation must link to its captured panel.',
        );
    }

    public function testConfigShowsFailedInertiaCaptureUnderExtensions(): void
    {
        $snapshot = new DebugSnapshot(
            $this->manifest()['request-1'],
            [],
            [
                'inertia' => PanelFailure::fromThrowable(
                    PanelFailure::CAPTURE,
                    new RuntimeException('Inertia capture failed.'),
                ),
            ],
        );

        $html = $this->rendererWithInertia()->config(
            'request-1',
            'light',
            $this->manifest(),
            $snapshot,
        );

        self::assertMatchesRegularExpression(
            '/yii-debug-nav-group.*Extensions.*Inertia/s',
            $html,
            'A failed Inertia capture must remain discoverable in the Extensions navigation group.',
        );
    }

    public function testConfigUsesCoreRendererAndDarkTheme(): void
    {
        $html = $this->renderer()->config(
            'request-1',
            'dark',
            $this->manifest(),
        );

        self::assertStringContainsString(
            '<title>Configuration — Yii Debugger</title>',
            $html,
            'Document title must identify the Configuration page.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            $html,
            'Document root must apply the requested dark theme.',
        );
        self::assertStringContainsString(
            'yii-debug-readout',
            $html,
            'Runtime summary must use shared readout cards.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-bar',
            $html,
            'Page must include the shared brand bar.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-yii',
            $html,
            'Brand bar must include the Yii chip.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-php',
            $html,
            'Brand bar must include the PHP chip.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-mem',
            $html,
            'Brand bar must include memory usage.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-config',
            $html,
            'Brand bar must include the configuration control.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-copy',
            $html,
            'Brand bar must include link copying.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-theme',
            $html,
            'Brand bar must include theme switching.',
        );
        self::assertStringContainsString(
            'Open configuration',
            $html,
            'Configuration control must expose its label.',
        );
        self::assertStringContainsString(
            '<aside class="yii-debug-sidebar">',
            $html,
            'Page must include the shared sidebar.',
        );
        self::assertStringContainsString(
            'Current request',
            $html,
            'Sidebar must identify the selected request.',
        );
        self::assertStringContainsString(
            'yii-debug-history-card',
            $html,
            'Sidebar must include the request summary card.',
        );
        self::assertStringContainsString(
            '/?page=2',
            $html,
            'Request card must preserve the captured query string.',
        );
        self::assertMatchesRegularExpression(
            '/yii-debug-request-nav.*History/s',
            $html,
            'Request card must precede history navigation.',
        );
        self::assertSame(
            1,
            substr_count($html, 'yii-debug-nav-link"'),
            'Sidebar must expose one navigation link.',
        );
        self::assertStringContainsString(
            'History',
            $html,
            'Sidebar navigation must expose request history.',
        );
        self::assertStringNotContainsString(
            'yii-debug-nav-group',
            $html,
            'Sidebar must omit the empty extensions group.',
        );
        self::assertStringContainsString(
            'Test application',
            $html,
            'Application metadata must remain visible.',
        );
        self::assertStringContainsString(
            '/debug/php-info',
            $html,
            'PHP chip must link to the PHP information page.',
        );
        self::assertStringContainsString(
            '/dist/css/debug.min.css',
            $html,
            'Page must load the shared debugger stylesheet.',
        );
        self::assertStringContainsString(
            '/dist/js/debug.min.js',
            $html,
            'Page must load the shared debugger runtime.',
        );
    }

    public function testHistoryAppliesRequestFilters(): void
    {
        $html = $this->renderer()->history(
            $this->manifest(),
            ['Debug' => ['method' => 'POST']],
            'light',
        );

        self::assertStringContainsString(
            'data-yii-debug-tag="request-2"',
            $html,
            'Matching request must remain in the filtered grid.',
        );
        self::assertStringNotContainsString(
            'data-yii-debug-tag="request-1"',
            $html,
            'Nonmatching request must be excluded from the filtered grid.',
        );
        self::assertStringContainsString(
            'Showing 1-1 of 1 items.',
            $html,
            'Pagination summary must reflect the filtered result count.',
        );
    }

    public function testHistoryRendersSummaryFiltersRowsAndNewestRequestSidebar(): void
    {
        $html = $this->renderer()->history($this->manifest(), [], 'dark');

        self::assertStringContainsString(
            '<title>Request history — Yii Debugger</title>',
            $html,
            'Document title must identify request history.',
        );
        self::assertStringContainsString(
            'captured requests',
            $html,
            'Summary must report the captured request count.',
        );
        self::assertStringContainsString(
            '</strong> 2xx',
            $html,
            'Summary must include successful responses.',
        );
        self::assertStringContainsString(
            '</strong> 4xx',
            $html,
            'Summary must include client error responses.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-history',
            $html,
            'Page must include the request history grid.',
        );
        self::assertStringContainsString(
            'name="Debug[tag]"',
            $html,
            'Grid must expose request tag filtering.',
        );
        self::assertStringContainsString(
            'name="Debug[method]"',
            $html,
            'Grid must expose request method filtering.',
        );
        self::assertStringContainsString(
            'data-yii-debug-tag="request-1"',
            $html,
            'Grid must include the successful request.',
        );
        self::assertStringContainsString(
            'data-yii-debug-tag="request-2"',
            $html,
            'Grid must include the client error request.',
        );
        self::assertStringContainsString(
            'Newest request',
            $html,
            'Sidebar must identify the newest request.',
        );
        self::assertMatchesRegularExpression(
            '/Newest request.*History/s',
            $html,
            'Newest request card must precede history navigation.',
        );
        self::assertStringContainsString(
            'yii-debug-nav-link is-active',
            $html,
            'History navigation link must be active.',
        );
        self::assertStringNotContainsString(
            '>Query</th>',
            $html,
            'Grid must omit the unavailable query column.',
        );
        self::assertStringNotContainsString(
            '>Mail</th>',
            $html,
            'Grid must omit the unavailable mail column.',
        );
    }

    public function testInertiaPanelIsActiveAndRequestNavigationRetainsPanel(): void
    {
        $snapshot = new DebugSnapshot(
            $this->manifest()['request-1'],
            ['inertia' => $this->inertiaPayload()],
            [],
        );

        $html = $this->rendererWithInertia()->extension(
            $snapshot,
            'inertia',
            'dark',
            $this->manifest(),
        );

        self::assertMatchesRegularExpression(
            '/<a(?=[^>]*href="\/debug\/view\?tag=request-1&amp;panel=inertia")'
            . '(?=[^>]*class="yii-debug-nav-link is-active")(?=[^>]*aria-current="page")[^>]*>.*?Inertia.*?<\/a>/s',
            $html,
            'The selected Inertia navigation link must be active.',
        );
        self::assertStringContainsString(
            '/debug/view?tag=request-2&amp;panel=inertia',
            $html,
            'Request navigation from an extension must retain the active Inertia panel.',
        );
    }

    public function testPhpInfoUsesCoreRenderer(): void
    {
        $html = $this->renderer()->phpInfo(
            'light',
            $this->manifest(),
        );

        self::assertStringContainsString(
            '<title>PHP Info — Yii Debugger</title>',
            $html,
            'Document title must identify the PHP information page.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="light"',
            $html,
            'Document root must apply the requested light theme.',
        );
        self::assertStringContainsString(
            'yii-debug-phpinfo-shell',
            $html,
            'PHP information output must use the shared content shell.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-bar',
            $html,
            'Page must include the shared brand bar.',
        );
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=config',
            $html,
            'Yii chip must link to the captured request configuration.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-mem',
            $html,
            'Brand bar must include memory usage.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-config',
            $html,
            'Brand bar must include the configuration control.',
        );
        self::assertStringContainsString(
            '<aside class="yii-debug-sidebar">',
            $html,
            'Page must include the shared sidebar.',
        );
        self::assertStringContainsString(
            'Current request',
            $html,
            'Sidebar must identify the selected request.',
        );
        self::assertStringContainsString(
            'yii-debug-history-card',
            $html,
            'Sidebar must include the request summary card.',
        );
        self::assertMatchesRegularExpression(
            '/yii-debug-request-nav.*History/s',
            $html,
            'Request card must precede history navigation.',
        );
        self::assertSame(
            1,
            substr_count($html, 'yii-debug-nav-link"'),
            'Sidebar must expose one navigation link.',
        );
        self::assertStringContainsString(
            'History',
            $html,
            'Sidebar navigation must expose request history.',
        );
        self::assertStringNotContainsString(
            'yii-debug-nav-group',
            $html,
            'Sidebar must omit the empty extensions group.',
        );
        self::assertStringContainsString(
            'data-yii-debug-phpinfo-search',
            $html,
            'PHP information page must expose its search control.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaPayload(): array
    {
        return InertiaSnapshot::capture(
            null,
            [
                'component' => 'Site/Index',
                'props' => ['appName' => 'Test application'],
                'url' => '/',
                'version' => 'version-1',
            ],
            ['X-Inertia' => 'true'],
            ['appName'],
            200,
        )->jsonSerialize();
    }

    /**
     * @return array{'request-1': RequestSummary, 'request-2': RequestSummary}
     */
    private function manifest(): array
    {
        return [
            'request-1' => RequestSummary::create('request-1')
                ->withRequest('https://example.test/?page=2', 'GET', '127.0.0.1', 1_725_000_756.0)
                ->withResponse(200)
                ->withProfiling(0.009, 1_145_324),
            'request-2' => RequestSummary::create('request-2')
                ->withRequest('https://example.test/missing', 'POST', '127.0.0.1', 1_725_000_700.0, true)
                ->withResponse(404)
                ->withProfiling(0.015, 2_097_152),
        ];
    }

    private function renderer(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-page-renderer-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(['name' => 'Test application']),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
        );
    }

    private function rendererWithInertia(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-page-renderer-inertia-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(['name' => 'Test application']),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            extensionPanels: [new InertiaPanel()],
        );
    }

    private function rendererWithVite(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-page-renderer-vite-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(['name' => 'Test application']),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            extensionPanels: [new VitePanel()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function vitePayload(): array
    {
        $viteSnapshot = new ViteSnapshot(
            [
                new ViteComponent(
                    id: 'vite',
                    class: Vite::class,
                    implementation: ViteComponent::IMPLEMENTATION_MODERN,
                    inspectionAvailable: true,
                    mode: ViteComponent::MODE_DEVELOPMENT,
                    entrypoints: ['resources/js/app.ts'],
                    baseUrl: '',
                    devServerUrl: 'http://127.0.0.1:5173',
                    manifestPath: '',
                    includeViteClient: true,
                    modulePreload: null,
                    chunks: [],
                ),
            ],
        );

        return $viteSnapshot->payload();
    }
}
