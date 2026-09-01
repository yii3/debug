<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPForge\Vite\Vite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Panel\{InertiaPanel, RequestPanel, VitePanel};
use Yii3\Debug\ToolbarDataFactory;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use function sys_get_temp_dir;

use const PHP_VERSION;

/**
 * Unit tests for the minimal toolbar payload.
 */
#[Group('toolbar')]
final class ToolbarDataFactoryTest extends TestCase
{
    public function testCreateExposesOnlyYiiPhpAndAjaxMetadata(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory($this->assetManager());

        $payload = $toolbarDataFactory
            ->create('request-1')
            ->jsonSerialize();

        self::assertSame(
            'request-1',
            $payload['tag'],
            'Toolbar payload must preserve the request tag.',
        );
        self::assertSame(
            '/debug',
            $payload['indexUrl'],
            'The toolbar title must link to request history.',
        );
        self::assertSame(
            '/debug/view?tag=request-1&panel=config',
            $payload['configUrl'],
            'The Yii chip must link to the live Configuration page.',
        );
        self::assertSame(
            [],
            $payload['items'],
            'No diagnostic panels must be exposed.',
        );
        self::assertSame(
            '/debug/php-info',
            $payload['phpInfoUrl'],
            'The PHP chip must link to phpinfo.',
        );
        self::assertSame(
            PHP_VERSION,
            $payload['phpVersion'],
            'Toolbar payload must expose the current PHP version.',
        );
        self::assertSame(
            '3',
            $payload['yiiVersion'],
            'Toolbar payload must expose the Yii major version.',
        );
        self::assertStringEndsWith(
            '/svg/',
            $payload['iconBaseUrl'],
            'AJAX icons must use the published SVG path.',
        );
    }

    public function testCreateForSnapshotExposesPanelFailuresAsDangerItems(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory(
            $this->assetManager(),
            extensionPanels: [new InertiaPanel()],
        );

        $failure = PanelFailure::fromThrowable(
            PanelFailure::CAPTURE,
            new RuntimeException('Unable to capture Inertia.'),
        );

        $snapshot = new DebugSnapshot(
            RequestSummary::create('request-1'),
            [],
            ['inertia' => $failure],
        );

        $payload = $toolbarDataFactory
            ->createForSnapshot($snapshot)
            ->jsonSerialize();

        self::assertSame(
            [
                [
                    'id' => 'inertia',
                    'title' => 'Inertia',
                    'url' => '/debug/view?tag=request-1&panel=inertia',
                    'items' => [
                        [
                            'label' => 'Inertia',
                            'value' => 'error',
                            'status' => 'danger',
                            'title' => 'Unable to capture Inertia.',
                        ],
                    ],
                ],
            ],
            $payload['items'],
            'A failed extension capture must match the Yii2 danger-item envelope.',
        );
    }

    public function testCreateForSnapshotExposesRequestBeforeExtensions(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory(
            $this->assetManager(),
            extensionPanels: [new RequestPanel(), new InertiaPanel()],
        );
        $snapshot = new DebugSnapshot(
            RequestSummary::create('request-1'),
            [
                'request' => RequestSnapshot::capture(['statusCode' => 201])->jsonSerialize(),
                'inertia' => $this->inertiaPayload('Site/Index'),
            ],
            [],
        );

        $payload = $toolbarDataFactory
            ->createForSnapshot($snapshot)
            ->jsonSerialize();

        self::assertSame(
            [
                [
                    'id' => 'request',
                    'title' => 'Request',
                    'url' => '/debug/view?tag=request-1&panel=request',
                    'icon' => 'request',
                    'items' => [
                        [
                            'value' => '201',
                            'status' => 'status-2xx',
                            'title' => 'Status code: 201 Created',
                        ],
                    ],
                ],
                [
                    'id' => 'inertia',
                    'title' => 'Inertia',
                    'url' => '/debug/view?tag=request-1&panel=inertia',
                    'icon' => 'inertia',
                    'items' => [
                        [
                            'value' => 'Site/Index',
                            'status' => 'default',
                            'title' => 'Inertia component',
                        ],
                    ],
                ],
            ],
            $payload['items'],
            'Request status must precede opt-in extension metrics like Yii2.',
        );
    }

    public function testCreateForSnapshotExposesTheInertiaComponentPanel(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory(
            $this->assetManager(),
            extensionPanels: [new InertiaPanel()],
        );

        $payload = $toolbarDataFactory
            ->withRoutePrefix('/developer/debug/')
            ->withPresentation('top', 65)
            ->createForSnapshot($this->snapshot($this->inertiaPayload('Site/Index')))
            ->jsonSerialize();

        self::assertSame(
            [
                [
                    'id' => 'inertia',
                    'title' => 'Inertia',
                    'url' => '/developer/debug/view?tag=request-1&panel=inertia',
                    'icon' => 'inertia',
                    'items' => [
                        [
                            'value' => 'Site/Index',
                            'status' => 'default',
                            'title' => 'Inertia component',
                        ],
                    ],
                ],
            ],
            $payload['items'],
            'A captured Inertia component must match the Yii2 toolbar panel contract.',
        );
        self::assertSame(
            'top',
            $payload['position'],
            'Immutable presentation changes must retain extension panels.',
        );
        self::assertSame(
            65,
            $payload['defaultHeight'],
            'Immutable height changes must retain extension panels.',
        );
    }

    public function testCreateForSnapshotExposesTheViteModePanel(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory(
            $this->assetManager(),
            extensionPanels: [new VitePanel()],
        );
        $snapshot = new DebugSnapshot(
            RequestSummary::create('request-1'),
            ['vite' => $this->vitePayload()],
            [],
        );

        $payload = $toolbarDataFactory
            ->createForSnapshot($snapshot)
            ->jsonSerialize();

        self::assertSame(
            [
                [
                    'id' => 'vite',
                    'title' => 'Vite',
                    'url' => '/debug/view?tag=request-1&panel=vite',
                    'icon' => 'brand-javascript',
                    'items' => [
                        [
                            'value' => 'Production',
                            'status' => 'default',
                            'title' => 'Vite mode',
                        ],
                    ],
                ],
            ],
            $payload['items'],
            'A captured Vite integration must match the Yii2 toolbar panel contract.',
        );
    }

    public function testCreateForSnapshotIsolatesMalformedInertiaPayloads(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory(
            $this->assetManager(),
            extensionPanels: [new InertiaPanel()],
        );
        $snapshot = new DebugSnapshot(
            RequestSummary::create('request-1'),
            ['inertia' => []],
            [],
        );

        $payload = $toolbarDataFactory
            ->createForSnapshot($snapshot)
            ->jsonSerialize();

        self::assertCount(
            1,
            $payload['items'],
            'A malformed extension must remain isolated to one toolbar panel.',
        );

        $panel = $payload['items'][0];

        self::assertArrayNotHasKey(
            'icon',
            $panel,
            'A failed extension panel must use the Yii2 error presentation.',
        );
        self::assertSame(
            'inertia',
            $panel['id'],
            'The failed toolbar panel must retain its stable identifier.',
        );
        self::assertCount(
            1,
            $panel['items'],
            'A malformed extension must expose one isolated error metric.',
        );

        $item = $panel['items'][0];

        self::assertSame(
            'danger',
            $item['status'],
            'A malformed captured payload must become a danger item instead of breaking the endpoint.',
        );
        self::assertSame(
            'error',
            $item['value'],
            'A malformed captured payload must expose an error value.',
        );
        self::assertArrayHasKey(
            'title',
            $item,
            'A malformed captured payload must expose its redacted diagnostic.',
        );
        self::assertStringContainsString(
            'Invalid debug snapshot',
            $item['title'],
            'The danger-item tooltip must contain the redacted hydration diagnostic.',
        );
    }

    public function testCreateForSnapshotOmitsInertiaWithoutAComponent(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory(
            $this->assetManager(),
            extensionPanels: [new InertiaPanel()],
        );

        $payload = $toolbarDataFactory
            ->createForSnapshot($this->snapshot($this->inertiaPayload(null)))
            ->jsonSerialize();

        self::assertSame(
            [],
            $payload['items'],
            'An Inertia capture without a component must not create an empty toolbar panel.',
        );
    }

    public function testCreateForwardsToolbarPresentationSettings(): void
    {
        $toolbarDataFactory = new ToolbarDataFactory($this->assetManager());

        $payload = $toolbarDataFactory
            ->withRoutePrefix('/developer/debug/')
            ->withPresentation('top', 65)
            ->create('request-1')
            ->jsonSerialize();

        self::assertSame(
            '/developer/debug/view?tag=request-1&panel=config',
            $payload['configUrl'],
            'Configured route prefix must be applied to the Yii chip URL.',
        );
        self::assertSame(
            '/developer/debug/php-info',
            $payload['phpInfoUrl'],
            'Configured route prefix must be applied to the PHP chip URL.',
        );
        self::assertSame(
            '/developer/debug',
            $payload['indexUrl'],
            'Configured route prefix must be normalized for the history URL.',
        );
        self::assertSame(
            'top',
            $payload['position'],
            'Configured toolbar position must reach the payload.',
        );
        self::assertSame(
            65,
            $payload['defaultHeight'],
            'Configured toolbar height must reach the payload.',
        );
    }

    private function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaPayload(string|null $component): array
    {
        return InertiaSnapshot::capture(
            null,
            $component === null
                ? null
                : [
                    'component' => $component,
                    'props' => [],
                    'url' => '/',
                    'version' => 'v1',
                ],
            [],
            [],
            200,
        )->jsonSerialize();
    }

    /**
     * @param array<string, mixed> $inertiaPayload
     */
    private function snapshot(array $inertiaPayload): DebugSnapshot
    {
        return new DebugSnapshot(
            RequestSummary::create('request-1'),
            ['inertia' => $inertiaPayload],
            [],
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
                    mode: ViteComponent::MODE_PRODUCTION,
                    entrypoints: ['resources/js/app.ts'],
                    baseUrl: '/build',
                    devServerUrl: null,
                    manifestPath: '/app/public/build/.vite/manifest.json',
                    includeViteClient: null,
                    modulePreload: true,
                    chunks: [],
                ),
            ],
        );

        return $viteSnapshot->jsonSerialize();
    }
}
