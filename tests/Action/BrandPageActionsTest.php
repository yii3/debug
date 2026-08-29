<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\{ConfigAction, HistoryAction, PhpInfoAction};
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function sys_get_temp_dir;

/**
 * Integration tests for the pages opened by the Yii and PHP toolbar chips.
 */
final class BrandPageActionsTest extends TestCase
{
    public function testConfigRejectsInvalidQueries(): void
    {
        $action = $this->configAction();

        $missingTag = $action(
            new ServerRequest('GET', '/debug/view?panel=config'),
        );
        $wrongPanel = $action(
            (new ServerRequest('GET', '/debug/view?tag=request-1&panel=request'))
                ->withQueryParams(['tag' => 'request-1', 'panel' => 'request']),
        );

        self::assertSame(
            400,
            $missingTag->getStatusCode(),
            'Missing request tag must produce a bad request response.',
        );
        self::assertSame(
            'A debug request tag is required.',
            (string) $missingTag->getBody(),
            'Missing request tag must produce an actionable error message.',
        );
        self::assertSame(
            400,
            $wrongPanel->getStatusCode(),
            'Unsupported panel must produce a bad request response.',
        );
        self::assertSame(
            'Only the configuration panel is available.',
            (string) $wrongPanel->getBody(),
            'Unsupported panel must identify the available panel.',
        );
    }

    public function testConfigRendersRequestedTheme(): void
    {
        $request = (new ServerRequest('GET', '/debug/view?tag=request-1&panel=config&yii_debug_theme=dark'))
            ->withQueryParams(
                [
                    'tag' => 'request-1',
                    'panel' => 'config',
                    'yii_debug_theme' => 'dark',
                ],
            );
        $response = ($this->configAction())($request);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Configuration page must return a successful response.',
        );
        self::assertSame(
            'text/html; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Configuration page must use the HTML content type.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            (string) $response->getBody(),
            'Document root must apply the requested dark theme.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-bar',
            (string) $response->getBody(),
            'Configuration page must include the shared brand bar.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-mem',
            (string) $response->getBody(),
            'Brand bar must include memory usage.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-config',
            (string) $response->getBody(),
            'Brand bar must include the configuration control.',
        );
        self::assertStringContainsString(
            'yii-debug-sidebar',
            (string) $response->getBody(),
            'Configuration page must include the shared sidebar.',
        );
        self::assertStringContainsString(
            'Current request',
            (string) $response->getBody(),
            'Sidebar must identify the selected request.',
        );
        self::assertStringContainsString(
            'yii-debug-history-card',
            (string) $response->getBody(),
            'Sidebar must include the request summary card.',
        );
        self::assertStringContainsString(
            'History',
            (string) $response->getBody(),
            'Sidebar must expose history navigation.',
        );
        self::assertStringContainsString(
            'yii-debug-readout',
            (string) $response->getBody(),
            'Configuration page must include runtime readouts.',
        );
    }

    public function testHistoryRendersStoredRequestsAndRequestedTheme(): void
    {
        $factory = new HttpFactory();
        $action = new HistoryAction($this->store(), $this->renderer(), $factory, $factory);
        $request = (new ServerRequest('GET', '/debug?yii_debug_theme=dark'))
            ->withQueryParams(['yii_debug_theme' => 'dark']);

        $response = $action($request);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'History page must return a successful response.',
        );
        self::assertSame(
            'text/html; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'History page must use the HTML content type.',
        );
        self::assertStringContainsString(
            '<title>Request history — Yii Debugger</title>',
            (string) $response->getBody(),
            'Document title must identify request history.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            (string) $response->getBody(),
            'History document must apply the requested dark theme.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-history',
            (string) $response->getBody(),
            'History page must include the request grid.',
        );
        self::assertStringContainsString(
            'data-yii-debug-tag="request-1"',
            (string) $response->getBody(),
            'Stored request must appear in the history grid.',
        );
    }

    public function testPhpInfoRendersRequestedTheme(): void
    {
        $request = (new ServerRequest('GET', '/debug/php-info?yii_debug_theme=dark'))
            ->withQueryParams(['yii_debug_theme' => 'dark']);

        $response = ($this->phpInfoAction())($request);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'PHP information page must return a successful response.',
        );
        self::assertSame(
            'text/html; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'PHP information page must use the HTML content type.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            (string) $response->getBody(),
            'PHP information document must apply the requested dark theme.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-bar',
            (string) $response->getBody(),
            'PHP information page must include the shared brand bar.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-mem',
            (string) $response->getBody(),
            'Brand bar must include memory usage.',
        );
        self::assertStringContainsString(
            'yii-debug-brand-chip-config',
            (string) $response->getBody(),
            'Brand bar must include the configuration control.',
        );
        self::assertStringContainsString(
            'yii-debug-sidebar',
            (string) $response->getBody(),
            'PHP information page must include the shared sidebar.',
        );
        self::assertStringContainsString(
            'Current request',
            (string) $response->getBody(),
            'Sidebar must identify the selected request.',
        );
        self::assertStringContainsString(
            'yii-debug-history-card',
            (string) $response->getBody(),
            'Sidebar must include the request summary card.',
        );
        self::assertStringContainsString(
            'History',
            (string) $response->getBody(),
            'Sidebar must expose history navigation.',
        );
        self::assertStringContainsString(
            'yii-debug-phpinfo-shell',
            (string) $response->getBody(),
            'PHP information output must use the shared content shell.',
        );
    }

    private function configAction(): ConfigAction
    {
        $factory = new HttpFactory();

        return new ConfigAction($this->store(), $this->renderer(), $factory, $factory);
    }

    private function phpInfoAction(): PhpInfoAction
    {
        $factory = new HttpFactory();

        return new PhpInfoAction($this->store(), $this->renderer(), $factory, $factory);
    }

    private function renderer(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-brand-actions-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
        );
    }

    private function store(): SnapshotStore
    {
        $store = new SnapshotStore(
            sys_get_temp_dir() . '/yii3-debug-brand-actions-' . uniqid(),
            0o700,
            0o600,
        );

        $store->writeSnapshot(
            new DebugSnapshot(
                RequestSummary::create('request-1')
                    ->withRequest('https://example.test/', 'GET', '127.0.0.1', 1_725_000_756.0)
                    ->withResponse(200)
                    ->withProfiling(0.009, 1_145_324),
                [],
                [],
            ),
            50,
        );

        return $store;
    }
}
