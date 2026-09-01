<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\{ConfigAction, HistoryAction, PhpInfoAction};
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Panel\{InertiaPanel, RequestPanel};
use Yii3\Debug\Tests\Support\HelperFactory;
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
    public function testAutomaticPanelFallsBackToConfigForALegacyCapture(): void
    {
        $response = ($this->configAction())(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=request-1&panel=auto',
            ),
        );

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Automatic legacy links must remain usable.',
        );
        self::assertStringContainsString(
            '<title>Configuration — Yii Debugger</title>',
            (string) $response->getBody(),
            'Automatic legacy links must fall back to Config when Request was not captured.',
        );
    }
    public function testConfigRejectsInvalidQueries(): void
    {
        $action = $this->configAction();

        $missingTag = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?panel=config',
            ),
        );
        $wrongPanel = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=request-1&panel=unknown',
            ),
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
            'The requested debug panel is not available.',
            (string) $wrongPanel->getBody(),
            'Unsupported panel must identify the available panel.',
        );
    }

    public function testConfigRendersRequestedTheme(): void
    {
        $request = HelperFactory::createRequest(
            'GET',
            '/debug/view?tag=request-1&panel=config&yii_debug_theme=dark',
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
        $action = new HistoryAction(
            $this->store(),
            $this->renderer(),
            HelperFactory::createResponseFactory(),
            HelperFactory::createStreamFactory(),
        );

        $request = HelperFactory::createRequest(
            'GET',
            '/debug?yii_debug_theme=dark',
        );

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

    public function testInertiaPanelRejectsMissingAndUncapturedSnapshots(): void
    {
        $action = $this->inertiaAction($this->store());

        $missing = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=missing&panel=inertia',
            ),
        );
        $uncaptured = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=request-1&panel=inertia',
            ),
        );

        self::assertSame(
            404,
            $missing->getStatusCode(),
            'An unknown request tag must produce a not found response.',
        );
        self::assertSame(
            'Debug snapshot not found.',
            (string) $missing->getBody(),
            'An unknown request tag must identify the missing snapshot.',
        );
        self::assertSame(
            404,
            $uncaptured->getStatusCode(),
            'A request without Inertia activity must produce a not found response.',
        );
        self::assertSame(
            'Debug panel was not captured.',
            (string) $uncaptured->getBody(),
            'An uncaptured Inertia panel must explain why it is unavailable.',
        );
    }

    public function testInertiaPanelRendersDirectlyForAnEmptyCaptureWithoutShowingExtensionNavigation(): void
    {
        $store = $this->store();

        $snapshot = $store->readSnapshot('request-1');

        self::assertNotNull(
            $snapshot,
            'The base debug snapshot fixture must exist.',
        );

        $store->writeSnapshot(
            new DebugSnapshot(
                $snapshot->summary,
                [
                    'inertia' => InertiaSnapshot::capture(
                        null,
                        null,
                        [],
                        [],
                        200,
                    )->jsonSerialize(),
                ],
                [],
            ),
            50,
        );

        $request = HelperFactory::createRequest(
            'GET',
            '/debug/view?tag=request-1&panel=inertia',
        );

        $response = ($this->inertiaAction($store))($request);

        $body = (string) $response->getBody();

        self::assertSame(
            200,
            $response->getStatusCode(),
            'A directly addressed empty capture must remain inspectable.',
        );
        self::assertStringContainsString(
            'No Inertia page in this request',
            $body,
            'The detail must explain that the request did not produce an Inertia page.',
        );
        self::assertStringNotContainsString(
            'yii-debug-nav-group',
            $body,
            'An empty Inertia capture must remain absent from extension navigation.',
        );
    }

    public function testInertiaPanelRendersRequestedDarkTheme(): void
    {
        $request = HelperFactory::createRequest(
            'GET',
            '/debug/view?tag=request-1&panel=inertia&yii_debug_theme=dark',
        );

        $response = ($this->inertiaAction($this->inertiaStore()))($request);

        self::assertSame(
            200,
            $response->getStatusCode(),
            'A captured Inertia panel must return a successful response.',
        );
        self::assertSame(
            'text/html; charset=UTF-8',
            $response->getHeaderLine('Content-Type'),
            'Inertia panel must use the HTML content type.',
        );
        self::assertStringContainsString(
            '<title>Inertia — Yii Debugger</title>',
            (string) $response->getBody(),
            'Document title must identify the Inertia panel.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            (string) $response->getBody(),
            'Inertia document must apply the requested dark theme.',
        );
        self::assertStringContainsString(
            'Site/Index',
            (string) $response->getBody(),
            'Inertia panel must render its captured component.',
        );
    }

    public function testPhpInfoRendersRequestedTheme(): void
    {
        $request = HelperFactory::createRequest(
            'GET',
            '/debug/php-info?yii_debug_theme=dark',
        );

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

    public function testRequestPanelRejectsMissingOrUncapturedSnapshots(): void
    {
        $action = $this->configAction();

        $missing = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=missing&panel=request',
            ),
        );
        $uncaptured = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=request-1&panel=request',
            ),
        );

        self::assertSame(
            404,
            $missing->getStatusCode(),
            'An unknown Request capture must return not found.',
        );
        self::assertSame(
            'Debug snapshot not found.',
            (string) $missing->getBody(),
            'An unknown Request capture must identify the missing snapshot.',
        );
        self::assertSame(
            404,
            $uncaptured->getStatusCode(),
            'A legacy capture without Request data must return not found.',
        );
        self::assertSame(
            'Debug panel was not captured.',
            (string) $uncaptured->getBody(),
            'A legacy capture must explain that Request data was not captured.',
        );
    }

    public function testRequestPanelRendersTheSharedDetailWithSummaryMetadata(): void
    {
        $store = $this->store();

        $snapshot = $store->readSnapshot('request-1');

        self::assertNotNull(
            $snapshot,
            'The base debug snapshot fixture must exist.',
        );

        $store->writeSnapshot(
            new DebugSnapshot(
                $snapshot->summary,
                [
                    'request' => RequestSnapshot::capture(
                        [
                            'action' => 'App\\Web\\HomeAction',
                            'actionParams' => [],
                            'flashes' => [],
                            'general' => ['method' => 'GET'],
                            'requestBody' => [],
                            'requestHeaders' => [],
                            'responseHeaders' => [],
                            'route' => 'home',
                            'statusCode' => 200,
                            'COOKIE' => [],
                            'FILES' => [],
                            'GET' => [],
                            'POST' => [],
                            'SERVER' => [],
                            'SESSION' => [],
                        ],
                    )->jsonSerialize(),
                ],
                [],
            ),
            50,
        );

        $request = HelperFactory::createRequest(
            'GET',
            '/debug/view?tag=request-1&panel=request&yii_debug_theme=dark',
        );

        $action = new ConfigAction(
            $store,
            $this->renderer(),
            HelperFactory::createResponseFactory(),
            HelperFactory::createStreamFactory(),
        );

        $response = $action($request);

        $automatic = $action(
            HelperFactory::createRequest(
                'GET',
                '/debug/view?tag=request-1&panel=auto',
            ),
        );

        $body = (string) $response->getBody();

        self::assertSame(
            200,
            $response->getStatusCode(),
            'Captured Request detail must be available.',
        );
        self::assertStringContainsString(
            '<title>Request — Yii Debugger</title>',
            $body,
            'Document title must identify Request.',
        );
        self::assertStringContainsString(
            'yii-debug-request-hero',
            $body,
            'Request detail must use the shared hero.',
        );
        self::assertStringContainsString(
            'https://example.test/',
            $body,
            'Request hero must use the captured summary URL.',
        );
        self::assertSame(
            200,
            $automatic->getStatusCode(),
            'Automatic links must select a captured Request panel.',
        );
        self::assertStringContainsString(
            '<title>Request — Yii Debugger</title>',
            (string) $automatic->getBody(),
            'Automatic links must prefer Request when the capture contains it.',
        );
    }

    private function configAction(): ConfigAction
    {
        return new ConfigAction(
            $this->store(),
            $this->renderer(),
            HelperFactory::createResponseFactory(),
            HelperFactory::createStreamFactory(),
        );
    }

    private function inertiaAction(SnapshotStore $store): ConfigAction
    {
        return new ConfigAction(
            $store,
            $this->rendererWithInertia(),
            HelperFactory::createResponseFactory(),
            HelperFactory::createStreamFactory(),
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

    private function inertiaStore(): SnapshotStore
    {
        $store = $this->store();

        $snapshot = $store->readSnapshot('request-1');

        self::assertNotNull(
            $snapshot,
            'The base debug snapshot fixture must exist.',
        );

        $store->writeSnapshot(
            new DebugSnapshot(
                $snapshot->summary,
                ['inertia' => $this->inertiaPayload()],
                [],
            ),
            50,
        );

        return $store;
    }

    private function phpInfoAction(): PhpInfoAction
    {
        return new PhpInfoAction(
            $this->store(),
            $this->renderer(),
            HelperFactory::createResponseFactory(),
            HelperFactory::createStreamFactory(),
        );
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

        return (
            new DebugPageRenderer(
                new WebView(),
                $assetManager,
                new ConfigDataFactory(),
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            )
        )->withExtensionPanels([new RequestPanel()]);
    }

    private function rendererWithInertia(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-brand-actions-inertia-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return (
            new DebugPageRenderer(
                new WebView(),
                $assetManager,
                new ConfigDataFactory(),
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            )
        )->withExtensionPanels([new RequestPanel(), new InertiaPanel()]);
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
