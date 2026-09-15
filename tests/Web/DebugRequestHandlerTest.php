<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Yii3\Debug\Action\{CompareAction, ConfigAction, DbExplainAction, HistoryAction, PhpInfoAction, ToolbarDataAction};
use Yii3\Debug\Tests\Provider\DebugRequestHandlerProvider;
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\Tests\Support\Stubs\{ContainerStub, DebugActionStub};
use Yii3\Debug\Web\DebugRequestHandler;

/**
 * Unit tests for {@see DebugRequestHandler} serving the debugger endpoints without the application router.
 *
 * {@see DebugRequestHandlerProvider} for test case data providers.
 */
#[Group('toolbar')]
final class DebugRequestHandlerTest extends TestCase
{
    /**
     * @param string $path Debugger path reaching the handler.
     * @param string $expectedAction Endpoint the path selects.
     */
    #[DataProviderExternal(DebugRequestHandlerProvider::class, 'paths')]
    public function testHandleDispatchesEachPathToItsEndpoint(string $path, string $expectedAction): void
    {
        $response = $this->handler()->handle(HelperFactory::createRequest('GET', $path));

        self::assertSame(
            200,
            $response->getStatusCode(),
            'A known path must reach its endpoint.',
        );
        self::assertSame(
            $expectedAction,
            $response->getHeaderLine('X-Debug-Action'),
            'Path must select its own endpoint.',
        );
        self::assertSame(
            'no-store',
            $response->getHeaderLine('Cache-Control'),
            'Captured data must never be cached.',
        );
    }

    public function testHandleForwardsTheRequestToTheEndpoint(): void
    {
        $response = $this->handler()->handle(HelperFactory::createRequest('GET', '/debug/view?tag=abc&panel=log'));

        self::assertSame(
            '/debug/view',
            (string) $response->getBody(),
            'Endpoint must receive the original request.',
        );
    }

    public function testHandleReturnsForbiddenWithoutABody(): void
    {
        $response = $this->handler()->forbidden();

        self::assertSame(
            403,
            $response->getStatusCode(),
            'A denied client must be rejected.',
        );
        self::assertSame(
            'no-store',
            $response->getHeaderLine('Cache-Control'),
            'Captured data must never be cached.',
        );
        self::assertSame(
            '',
            (string) $response->getBody(),
            'Rejection must expose no diagnostics.',
        );
    }

    public function testHandleReturnsMethodNotAllowedForWriteMethods(): void
    {
        $response = $this->handler()->handle(HelperFactory::createRequest('POST', '/debug'));

        self::assertSame(
            405,
            $response->getStatusCode(),
            'The debugger is read-only.',
        );
        self::assertSame(
            'GET, HEAD',
            $response->getHeaderLine('Allow'),
            'Rejection must advertise the readable methods.',
        );
        self::assertSame(
            'no-store',
            $response->getHeaderLine('Cache-Control'),
            'Captured data must never be cached.',
        );
        self::assertSame(
            '',
            (string) $response->getBody(),
            'Rejection must expose no diagnostics.',
        );
    }

    public function testHandleReturnsNotFoundForAnUnknownPath(): void
    {
        $response = $this->handler()->handle(HelperFactory::createRequest('GET', '/debug/nope'));

        self::assertSame(
            404,
            $response->getStatusCode(),
            'An unknown path must not reach an endpoint.',
        );
        self::assertSame(
            'no-store',
            $response->getHeaderLine('Cache-Control'),
            'Captured data must never be cached.',
        );
        self::assertSame(
            '',
            (string) $response->getBody(),
            'Rejection must expose no diagnostics.',
        );
    }

    public function testHandleReturnsNotFoundForAPathOutsideThePrefix(): void
    {
        $response = $this->handler()->handle(HelperFactory::createRequest('GET', '/compare'));

        self::assertSame(
            404,
            $response->getStatusCode(),
            'A path the debugger does not own must not reach an endpoint.',
        );
    }

    public function testHandleServesHeadAndLowercaseReadMethods(): void
    {
        $head = $this->handler()->handle(HelperFactory::createRequest('HEAD', '/debug'));
        $lowercase = $this->handler()->handle(HelperFactory::createRequest('get', '/debug'));

        self::assertSame(
            200,
            $head->getStatusCode(),
            '`HEAD` must be readable.',
        );
        self::assertSame(
            200,
            $lowercase->getStatusCode(),
            'Method comparison must be case-insensitive.',
        );
    }

    public function testThrowRuntimeExceptionWhenTheContainerResolvesAnotherService(): void
    {
        $handler = new DebugRequestHandler(
            new ContainerStub([HistoryAction::class => new stdClass()]),
            HelperFactory::createResponseFactory(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The debug service Yii3\Debug\Action\HistoryAction is not available.',
        );

        $handler->handle(HelperFactory::createRequest('GET', '/debug'));
    }

    public function testWithRoutePrefixReturnsANewInstanceAndTrimsTheTrailingSlash(): void
    {
        $handler = $this->handler();
        $relocated = $handler->withRoutePrefix('/developer/debug/');

        self::assertNotSame(
            $handler,
            $relocated,
            'Should return a new instance when setting the route prefix, ensuring immutability.',
        );
        self::assertSame(
            'config',
            $relocated
                ->handle(HelperFactory::createRequest('GET', '/developer/debug/view'))
                ->getHeaderLine('X-Debug-Action'),
            'Trailing slash must be trimmed from the prefix.',
        );
        self::assertSame(
            404,
            $relocated->handle(HelperFactory::createRequest('GET', '/debug'))->getStatusCode(),
            'The former prefix must stop matching.',
        );
    }

    private function handler(): DebugRequestHandler
    {
        return new DebugRequestHandler(
            new ContainerStub(
                [
                    CompareAction::class => new DebugActionStub('compare'),
                    ConfigAction::class => new DebugActionStub('config'),
                    DbExplainAction::class => new DebugActionStub('db-explain'),
                    HistoryAction::class => new DebugActionStub('history'),
                    PhpInfoAction::class => new DebugActionStub('php-info'),
                    ToolbarDataAction::class => new DebugActionStub('toolbar'),
                ],
            ),
            HelperFactory::createResponseFactory(),
        );
    }
}
