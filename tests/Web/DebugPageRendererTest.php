<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use InvalidArgumentException;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Asset\Icon;
use Yii3\Debug\Panel\{ContextAwarePanelInterface, PanelContentTrait, PanelInterface};
use Yii3\Debug\Tests\Support\{CustomPanel, GridFactory};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

/**
 * Unit tests for {@see DebugPageRenderer} escaping request history and snapshot payloads.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class DebugPageRendererTest extends TestCase
{
    public function testHistoryEscapesCapturedUrlAndLinksSnapshot(): void
    {
        $summary = $this->summary();
        $html = $this->renderer()->history([$summary->tag => $summary]);

        self::assertStringContainsString(
            'https://example.test/?value=&lt;script&gt;',
            $html,
            'Captured URL must be escaped before rendering.',
        );
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=request',
            $html,
            'History row must link to the request panel.',
        );
        self::assertStringContainsString(
            'class="yii-debug-brand-bar"',
            $html,
            'Yii3 history must use the shared debugger brand bar.',
        );
        self::assertStringContainsString(
            '/dist/css/debug.min.css"',
            $html,
            'Yii3 asset definition must publish the shared stylesheet.',
        );
        self::assertStringContainsString(
            '/dist/js/debug.min.js"',
            $html,
            'Yii3 asset definition must publish the shared runtime.',
        );
        self::assertStringContainsString(
            'type="module"',
            $html,
            'Full-page runtime must load as an ES module.',
        );
    }

    public function testHistoryRendersFilterableGridWithCursorContract(): void
    {
        $summary = $this->summary();

        $html = $this->renderer()->history(
            [$summary->tag => $summary],
            ['Debug' => ['url' => 'example']],
            'dark',
        );

        self::assertStringContainsString('yii-debug-filter-cell', $html, 'Filter row cells must carry the class.');
        self::assertStringContainsString('name="Debug[url]"', $html, 'Filter inputs must use the Debug prefix.');
        self::assertStringContainsString('value="example"', $html, 'Active filter values must round-trip.');
        self::assertStringContainsString(
            'data-yii-debug-tag="request-1"',
            $html,
            'Rows must carry the cursor tag attribute.',
        );
        self::assertStringContainsString(
            'data-yii-debug-history-cursor',
            $html,
            'Sidebar must emit the history-cursor block.',
        );
        self::assertStringContainsString('yii-debug-grid-summary', $html, 'Summary strip must render.');
        self::assertStringContainsString('data-yii-debug-pagesize', $html, 'Page-size selector must render.');
        self::assertStringContainsString('Showing 1-1 of 1 items.', $html, 'Footer count must render.');
        self::assertStringContainsString('1 filter active', $html, 'Active-filter banner must render.');
        self::assertStringContainsString('data-yii-debug-theme="dark"', $html, 'Resolved theme must reach the layout.');
    }

    public function testHistorySidebarListsRegisteredPanelsOnNewestRequest(): void
    {
        $summary = $this->summary();

        $html = $this->renderer([new CustomPanel()])->history([$summary->tag => $summary]);

        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=app.example',
            $html,
            'Sidebar must link every registered panel to the newest request.',
        );
        self::assertStringContainsString(
            'Open this panel on the newest request',
            $html,
            'Index nav items must carry the newest-request tooltip.',
        );
    }

    public function testSnapshotDefaultsToRequestPanelWhenAvailable(): void
    {
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['request' => ['marker' => 'request-marker-payload']],
            [],
        );

        $html = $this->renderer()->snapshot($snapshot, null);

        self::assertStringContainsString(
            'request-marker-payload',
            $html,
            'Missing panel selection must land on the request panel.',
        );
    }

    public function testSnapshotFallsBackToEscapedJsonWhenPanelRendererFails(): void
    {
        $panel = new readonly class implements PanelInterface {
            use PanelContentTrait;

            public function id(): string
            {
                return 'app.throwing';
            }

            public function name(): string
            {
                return 'Throwing panel';
            }

            public function icon(): string|null
            {
                return null;
            }

            public function render(array $payload): string
            {
                throw new RuntimeException('<renderer failed>');
            }

            public function toolbarItems(array $payload): array
            {
                return [];
            }
        };
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['app.throwing' => ['value' => '</pre><script>alert(1)</script>']],
            [],
        );

        $html = $this->renderer([$panel])->snapshot($snapshot, 'app.throwing');

        self::assertStringContainsString('Panel rendering failed.', $html, 'Rendering failure must be shown explicitly.');
        self::assertStringContainsString('&lt;renderer failed&gt;', $html, 'Renderer error message must be escaped.');
        self::assertStringContainsString(
            '&lt;/pre&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Renderer failure must preserve the escaped JSON fallback.',
        );
    }

    public function testSnapshotHidesRegisteredPanelsWithoutContentFromTheNav(): void
    {
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['app.example' => []],
            [],
        );

        $html = $this->renderer([new CustomPanel()])->snapshot($snapshot, 'summary');

        self::assertStringNotContainsString(
            '/debug/view?tag=request-1&amp;panel=app.example',
            $html,
            'Panels without content must be hidden from the sidebar nav.',
        );
    }

    public function testSnapshotRendersUnknownPanelAsEscapedJson(): void
    {
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['app.unknown' => ['value' => '</pre><script>alert(1)</script>']],
            [],
        );

        $html = $this->renderer()->snapshot($snapshot, 'app.unknown');

        self::assertStringContainsString('App Unknown', $html, 'Unknown panel must receive a readable fallback label.');
        self::assertStringContainsString(
            '&lt;/pre&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Stored JSON must not break out of the payload block.',
        );
        self::assertStringNotContainsString(
            '</pre><script>alert(1)</script>',
            $html,
            'Raw stored markup must not reach the document.',
        );
    }

    public function testSnapshotShowsFailureWithoutPanelPayload(): void
    {
        $snapshot = new DebugSnapshot(
            $this->summary(),
            [],
            [
                'app.failed' => PanelFailure::fromThrowable(
                    PanelFailure::CAPTURE,
                    new RuntimeException('Collector failed.'),
                ),
            ],
        );

        $html = $this->renderer()->snapshot($snapshot, 'app.failed');

        self::assertStringContainsString('App Failed', $html, 'Failure-only panel ID must remain navigable.');
        self::assertStringContainsString('Panel capture failed.', $html, 'Capture failure must be rendered explicitly.');
        self::assertStringContainsString('Collector failed.', $html, 'Captured exception must remain inspectable.');
    }

    public function testSnapshotSuppliesContextToContextAwarePanel(): void
    {
        $panel = new readonly class implements ContextAwarePanelInterface {
            use PanelContentTrait;

            public function icon(): string|null
            {
                return null;
            }

            public function id(): string
            {
                return 'app.context';
            }

            public function name(): string
            {
                return 'Context panel';
            }

            public function render(array $payload): string
            {
                return 'legacy fallback';
            }

            public function renderWithContext(array $payload, PanelRenderContext $context): string
            {
                return '<div class="context-panel">'
                    . $context->tag . '|'
                    . $context->panel . '|'
                    . $context->theme . '|'
                    . $context->panelUrl(queryParams: []) . '|'
                    . $context->historyUrl([]) . '|'
                    . $context->actionUrl('download', ['file' => 'report.txt']) . '|'
                    . Coerce::string($context->panelPayload('app.context')['value'] ?? null)
                    . '</div>';
            }

            public function toolbarItems(array $payload): array
            {
                return [];
            }
        };
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['app.context' => ['value' => 'stored']],
            [],
        );

        $html = $this->renderer([$panel])->snapshot(
            $snapshot,
            'app.context',
            queryParams: ['page' => 2],
            theme: 'dark',
        );

        self::assertStringContainsString(
            '<div class="context-panel">request-1|app.context|dark|'
                    . '/debug/view?tag=request-1&panel=app.context|/debug|'
                    . '/debug/download?tag=request-1&file=report.txt|stored</div>',
            $html,
            'Context-aware panels must receive the tag, panel, theme, adapter-owned URLs, and sibling payloads.',
        );
        self::assertStringNotContainsString(
            'legacy fallback',
            $html,
            'The context-free fallback must not run for context-aware panels.',
        );
    }

    public function testSnapshotUsesConfiguredPanelRenderer(): void
    {
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['app.example' => ['value' => 'custom payload']],
            [],
        );

        $html = $this->renderer([new CustomPanel()])->snapshot($snapshot, 'app.example');

        self::assertStringContainsString('Application example', $html, 'Configured panel name must replace fallback metadata.');
        self::assertStringContainsString(
            '<div class="app-example-panel">',
            $html,
            'Configured panel renderer must replace the JSON fallback.',
        );
        self::assertStringContainsString('custom payload', $html, 'Configured panel must receive the stored payload.');
    }

    public function testThrowInvalidArgumentExceptionForDuplicatePanelId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate Yii3 debug panel ID: app.example.');

        $this->renderer([new CustomPanel(), new CustomPanel()]);
    }

    public function testThrowInvalidArgumentExceptionWhenPanelIdIsEmpty(): void
    {
        $panel = new readonly class implements PanelInterface {
            use PanelContentTrait;

            public function id(): string
            {
                return '   ';
            }

            public function name(): string
            {
                return 'Invalid';
            }

            public function icon(): string|null
            {
                return null;
            }

            public function render(array $payload): string
            {
                return '';
            }

            public function toolbarItems(array $payload): array
            {
                return [];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Yii3 debug panel ID must not be empty.');

        $this->renderer([$panel]);
    }

    /**
     * Creates a renderer with an in-place test asset loader.
     *
     * @param iterable<PanelInterface> $panels Configured presentation panels.
     *
     * @return DebugPageRenderer Renderer under test.
     */
    private function renderer(iterable $panels = []): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new Icon($aliases),
            GridFactory::panelGrid(),
            $aliases,
            panels: $panels,
        );
    }

    /**
     * Creates representative request metadata.
     *
     * @return RequestSummary Representative request summary.
     */
    private function summary(): RequestSummary
    {
        return new RequestSummary(
            tag: 'request-1',
            url: 'https://example.test/?value=<script>',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: 0.01,
            peakMemory: 2_097_152,
        );
    }
}
