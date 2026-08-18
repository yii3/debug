<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

/**
 * Unit tests for {@see ToolbarRenderer} rendering shared markup with Yii3 assets.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarRendererTest extends TestCase
{
    public function testInjectPlacesToolbarBeforeClosingBody(): void
    {
        $html = $this->renderer()->inject('<html><body>Page</body></html>', '<toolbar></toolbar>');

        self::assertSame(
            '<html><body>Page<toolbar></toolbar></body></html>',
            $html,
            'Toolbar must be inserted before the final closing body tag.',
        );
    }

    public function testRenderUsesSharedTemplateAndYii3PublishedRuntime(): void
    {
        $html = $this->renderer()->render(
            '/debug/toolbar?tag=request-1&value=<unsafe>',
            ['/debug/poll?value="unsafe"'],
            'upper',
            60,
        );

        self::assertStringContainsString('<yii-debug-toolbar', $html, 'Shared custom element must be rendered.');
        self::assertStringContainsString(
            'data-url="/debug/toolbar?tag=request-1&amp;value=&lt;unsafe&gt;"',
            $html,
            'Toolbar data URL must be escaped.',
        );
        self::assertStringContainsString('data-position="upper"', $html, 'Toolbar position must be rendered.');
        self::assertStringContainsString('data-height="60"', $html, 'Toolbar height must be rendered.');
        self::assertStringContainsString(
            '/dist/js/toolbar.min.js"></script>',
            $html,
            'Yii3 asset publisher must provide the toolbar runtime URL.',
        );
        self::assertStringContainsString(
            '<script type="module" src="',
            $html,
            'Toolbar runtime must load as an ES module.',
        );
    }

    /**
     * Creates a renderer with an in-place test asset loader.
     *
     * @return ToolbarRenderer Renderer under test.
     */
    private function renderer(): ToolbarRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-renderer-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new ToolbarRenderer(new WebView(), $assetManager, $aliases->get('@yii3DebugViews'));
    }
}
