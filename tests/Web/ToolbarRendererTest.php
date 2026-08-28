<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see ToolbarRenderer} rendering shared markup with Yii3 assets.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarRendererTest extends TestCase
{
    public function testInjectAppendsToolbarWhenDocumentHasNoClosingBody(): void
    {
        self::assertSame(
            '<main>Fragment</main><toolbar></toolbar>',
            $this->renderer()->inject('<main>Fragment</main>', '<toolbar></toolbar>'),
            'A bodyless HTML fragment must receive the toolbar at the end.',
        );
    }

    public function testInjectPlacesToolbarBeforeClosingBody(): void
    {
        $html = $this->renderer()->inject('<html><body>Page</body></html>', '<toolbar></toolbar>');

        self::assertSame(
            '<html><body>Page<toolbar></toolbar></body></html>',
            $html,
            'Toolbar must be inserted before the final closing body tag.',
        );
    }

    public function testRenderElementTrimsWhitespaceProducedByTheView(): void
    {
        $viewPath = sys_get_temp_dir() . '/yii3-debug-toolbar-view-' . uniqid('', true);
        mkdir($viewPath, 0o700, true);
        file_put_contents($viewPath . '/toolbar.php', " \n<yii-debug-toolbar></yii-debug-toolbar>\n ");

        try {
            $element = $this->renderer(viewPath: $viewPath)->renderElement('/debug/toolbar');
        } finally {
            unlink($viewPath . '/toolbar.php');
            rmdir($viewPath);
        }

        self::assertSame(
            '<yii-debug-toolbar></yii-debug-toolbar>',
            $element,
            'Element rendering must remove whitespace emitted around a custom view.',
        );
    }

    public function testRenderUsesSharedTemplateAndYii3PublishedRuntime(): void
    {
        $renderer = $this->renderer();
        $html = $renderer->render(
            "/debug/toolbar?tag=request-1&value=<unsafe>'",
            ['/debug/poll?value="unsafe"'],
            'upper',
            60,
        );

        self::assertStringContainsString('<yii-debug-toolbar', $html, 'Shared custom element must be rendered.');
        self::assertStringContainsString(
            'data-url="/debug/toolbar?tag=request-1&amp;value=&lt;unsafe&gt;&apos;"',
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
        $elementPosition = strpos($html, '<yii-debug-toolbar');
        $scriptPosition = strpos($html, '<script type="module"');
        self::assertIsInt($elementPosition, 'Rendered output must contain the toolbar element.');
        self::assertIsInt($scriptPosition, 'Rendered output must contain the runtime script.');
        self::assertLessThan(
            $scriptPosition,
            $elementPosition,
            'The custom element must precede the runtime script that upgrades it.',
        );
        self::assertSame($html, trim($html), 'Rendered toolbar markup must not include surrounding whitespace.');

        $element = $renderer->renderElement('/debug/toolbar?tag=request-2');
        self::assertStringContainsString('data-height="50"', $element, 'Public element rendering must retain its default height.');
        self::assertStringNotContainsString('<script', $element, 'Element-only rendering must not duplicate the runtime script.');

        $script = $renderer->scriptTag();
        self::assertStringStartsWith('<script type="module"', $script, 'Public script rendering must return the module tag.');
        self::assertStringNotContainsString('<yii-debug-toolbar', $script, 'Script-only rendering must not include the element.');
        self::assertStringContainsString(
            '/debug-&quot;assets/',
            $this->renderer('/debug-"assets')->scriptTag(),
            'Published runtime URLs must be escaped before entering the script attribute.',
        );

        $defaultHtml = $renderer->render('/debug/toolbar?tag=request-3');
        self::assertStringContainsString('data-height="50"', $defaultHtml, 'Combined rendering must retain its default height.');
    }

    /**
     * Creates a renderer with an in-place test asset loader.
     *
     * @return ToolbarRenderer Renderer under test.
     */
    private function renderer(string $assetsUrl = '/debug-assets', string|null $viewPath = null): ToolbarRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-renderer-assets',
                '@assetsUrl' => $assetsUrl,
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new ToolbarRenderer(new WebView(), $assetManager, $viewPath ?? $aliases->get('@yii3DebugViews'));
    }
}
