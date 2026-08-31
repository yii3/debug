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
        self::assertStringContainsString(
            'No Vite integrations captured',
            $this->panel->render($payload),
            'Direct access to an empty capture must retain the shared diagnostic state.',
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

        self::assertStringContainsString(
            'Production',
            $html,
            'The runtime mode must surface in the panel.',
        );
        self::assertStringContainsString(
            'frontend',
            $html,
            'The configured component ID must remain visible.',
        );
        self::assertStringContainsString(
            'resources/js/app.ts',
            $html,
            'Configured entrypoints and manifest chunk names must remain visible.',
        );
        self::assertStringContainsString(
            '/build',
            $html,
            'The normalized asset base URL must remain visible.',
        );
        self::assertStringContainsString(
            '/app/public/build/.vite/manifest.json',
            $html,
            'The manifest path must remain visible.',
        );
        self::assertStringContainsString(
            'assets/app.js',
            $html,
            'The emitted chunk file must remain visible.',
        );
        self::assertStringContainsString(
            'Enabled',
            $html,
            'The module-preload setting must remain visible.',
        );
        self::assertStringContainsString(
            'Not applicable',
            $html,
            'Production must mark the Vite client as inapplicable.',
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

        return $viteSnapshot->payload();
    }
}
