<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Asset\ViteChunk;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPForge\Vite\Vite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\VitePanel;

use function array_map;
use function array_values;

/**
 * Unit tests for the stateless Vite extension-panel presentation.
 */
#[Group('vite')]
final class VitePanelTest extends TestCase
{
    private VitePanel $panel;

    public function testContractMetadataAndPerCaptureVisibility(): void
    {
        self::assertSame(
            'vite',
            $this->panel->id(),
            'Panel ID must match the persisted payload key.',
        );
        self::assertSame(
            'Vite',
            $this->panel->name(),
            'Panel name must match the sidebar label.',
        );
        self::assertSame(
            'brand-javascript',
            $this->panel->icon(),
            'Panel icon must use the shared JavaScript brand glyph.',
        );
        self::assertFalse(
            $this->panel->hasContent($this->payload()),
            'An empty Vite snapshot must stay out of the Extensions navigation group.',
        );
        self::assertTrue(
            $this->panel->hasContent($this->payload($this->component())),
            'A captured Vite integration must expose the panel.',
        );
    }

    public function testEmptyCaptureRendersDiagnosticsWithoutAToolbarItem(): void
    {
        $payload = $this->payload();

        self::assertSame(
            [],
            $this->panel->toolbarItems($payload),
            'An empty Vite capture must stay out of the toolbar.',
        );
        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Vite
            </h1><header class="yii-debug-grid-summary">
            <span><strong>0</strong> components</span>
            </header><div class="yii-debug-empty-state">
            <h2>
            No Vite integrations captured
            </h2><p>
            This request did not use an initialized Vite application component.
            </p>
            </div>
            HTML,
            $this->panel->render($payload),
            'Direct access to an empty capture must render the complete shared diagnostic state.',
        );
    }

    public function testProductionDetailRendersConfigurationAndChunks(): void
    {
        $html = $this->panel->render(
            $this->payload(
                $this->component(
                    id: 'frontend',
                    mode: ViteComponent::MODE_PRODUCTION,
                    entrypoints: ['resources/js/app.ts'],
                    baseUrl: '/build',
                    devServerUrl: null,
                    manifestPath: '/app/public/build/.vite/manifest.json',
                    includeViteClient: null,
                    modulePreload: true,
                    chunks: [
                        new ViteChunk('resources/js/app.ts', 'assets/app.js', 2, 1, true),
                    ],
                ),
            ),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Vite
            </h1><header class="yii-debug-grid-summary">
            <span><strong>1</strong> component</span><span class="yii-debug-grid-summary-sep">·</span><span>Production</span>
            </header><section class="yii-debug-vite-component" aria-label="Vite component frontend">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono yii-debug-table-vite-overview">
            <tbody>
            <tr>
            <th scope="row">
            Component ID
            </th><td>
            frontend
            </td>
            </tr><tr>
            <th scope="row">
            Class
            </th><td>
            PHPForge\Vite\Vite
            </td>
            </tr><tr>
            <th scope="row">
            Implementation
            </th><td>
            modern
            </td>
            </tr><tr>
            <th scope="row">
            Mode
            </th><td>
            Production
            </td>
            </tr><tr>
            <th scope="row">
            Inspection
            </th><td>
            <span class="yii-debug-badge yii-debug-badge-success">Available</span>
            </td>
            </tr><tr>
            <th scope="row">
            Entry points
            </th><td>
            resources/js/app.ts
            </td>
            </tr><tr>
            <th scope="row">
            Base URL
            </th><td>
            /build
            </td>
            </tr><tr>
            <th scope="row">
            Dev server
            </th><td>
            —
            </td>
            </tr><tr>
            <th scope="row">
            Manifest
            </th><td>
            /app/public/build/.vite/manifest.json
            </td>
            </tr><tr>
            <th scope="row">
            Vite client
            </th><td>
            Not applicable
            </td>
            </tr><tr>
            <th scope="row">
            Module preload
            </th><td>
            Enabled
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-section-header">
            <h2>
            Build chunks
            </h2>
            </div><div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Chunk
            </th><th scope="col">
            Output
            </th><th scope="col">
            CSS
            </th><th scope="col">
            Imports
            </th><th scope="col">
            Entry
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td class="yii-debug-cell-mono">
            <strong>resources/js/app.ts</strong>
            </td><td class="yii-debug-cell-mono">
            assets/app.js
            </td><td class="yii-debug-cell-numeric">
            2
            </td><td class="yii-debug-cell-numeric">
            1
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-success">entry</span>
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </section>
            HTML,
            $html,
            'Production detail must match the complete shared Vite panel markup.',
        );
    }

    public function testToolbarItemsExposeSingleAndMixedRuntimeModes(): void
    {
        $single = $this->panel->toolbarItems(
            $this->payload($this->component(mode: ViteComponent::MODE_DEVELOPMENT)),
        );
        $mixed = $this->panel->toolbarItems(
            $this->payload(
                $this->component(id: 'frontend', mode: ViteComponent::MODE_DEVELOPMENT),
                $this->component(id: 'admin', mode: ViteComponent::MODE_PRODUCTION),
            ),
        );

        self::assertSame(
            [
                [
                    'value' => 'Development',
                    'status' => 'default',
                    'title' => 'Vite mode',
                ],
            ],
            array_map(static fn(ToolbarItem $item): array => $item->jsonSerialize(), $single),
            'A single Vite integration must expose its runtime mode.',
        );
        self::assertSame(
            [
                [
                    'value' => '2 components · Mixed',
                    'status' => 'default',
                    'title' => 'Vite mode',
                ],
            ],
            array_map(static fn(ToolbarItem $item): array => $item->jsonSerialize(), $mixed),
            'Multiple Vite integrations must expose their count and aggregate mode.',
        );
    }

    protected function setUp(): void
    {
        $this->panel = new VitePanel();
    }

    /**
     * @param list<string> $entrypoints
     * @param list<ViteChunk> $chunks
     */
    private function component(
        string $id = 'vite',
        string $mode = ViteComponent::MODE_DEVELOPMENT,
        array $entrypoints = ['resources/js/app.ts'],
        string $baseUrl = '',
        string|null $devServerUrl = 'http://127.0.0.1:5173',
        string $manifestPath = '',
        bool|null $includeViteClient = true,
        bool|null $modulePreload = null,
        array $chunks = [],
    ): ViteComponent {
        return new ViteComponent(
            id: $id,
            class: Vite::class,
            implementation: ViteComponent::IMPLEMENTATION_MODERN,
            inspectionAvailable: true,
            mode: $mode,
            entrypoints: $entrypoints,
            baseUrl: $baseUrl,
            devServerUrl: $devServerUrl,
            manifestPath: $manifestPath,
            includeViteClient: $includeViteClient,
            modulePreload: $modulePreload,
            chunks: $chunks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ViteComponent ...$components): array
    {
        $viteSnapshot = new ViteSnapshot(array_values($components));

        return $viteSnapshot->jsonSerialize();
    }
}
