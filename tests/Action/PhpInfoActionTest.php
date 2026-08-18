<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\PhpInfoAction;
use Yii3\Debug\Asset\Icon;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ResponseBuilder};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function dirname;
use function sys_get_temp_dir;

/**
 * Unit tests for {@see PhpInfoAction} serving the standalone phpinfo report.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class PhpInfoActionTest extends TestCase
{
    public function testInvokeRendersReportForAllowedClient(): void
    {
        $request = new ServerRequest(
            'GET',
            'https://example.test/debug/php-info',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $response = $this->action()($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode(), 'Allowed client must receive the report.');
        self::assertStringContainsString(
            'text/html',
            $response->getHeaderLine('Content-Type'),
            'Media type must be HTML.',
        );
        self::assertStringContainsString(
            '<h1 class="yii-debug-hero-title">phpinfo</h1>',
            $html,
            'Hero title must be present.',
        );
        self::assertStringContainsString(
            'class="yii-debug-brand-bar"',
            $html,
            'Report must render inside the shared debugger shell.',
        );
        self::assertStringContainsString(
            PHP_VERSION,
            $html,
            'Runtime PHP version must surface in the report.',
        );
    }

    public function testInvokeReturnsForbiddenForDisallowedClient(): void
    {
        $request = new ServerRequest(
            'GET',
            'https://example.test/debug/php-info',
            serverParams: ['REMOTE_ADDR' => '203.0.113.7'],
        );

        $response = $this->action()($request);

        self::assertSame(403, $response->getStatusCode(), 'Disallowed client must be rejected.');
    }

    /**
     * Creates the action with an isolated renderer and response builder.
     *
     * @return PhpInfoAction Configured action.
     */
    private function action(): PhpInfoAction
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-phpinfo-action-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
                '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
        $factory = new HttpFactory();

        return new PhpInfoAction(
            new LocalAccessChecker(),
            new DebugPageRenderer(new WebView(), $assetManager, new Icon($aliases), GridFactory::panelGrid(), $aliases),
            new ResponseBuilder($factory, $factory),
        );
    }
}
