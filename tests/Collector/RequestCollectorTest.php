<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use GuzzleHttp\Psr7\{Response, ServerRequest};
use LogicException;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yii3\Debug\Collector\RequestCollector;
use Yiisoft\Router\{CurrentRoute, Route, RouteCollection, RouteCollector};

/**
 * Unit tests for {@see RequestCollector} capturing PSR-7 metadata into the shared Request panel payload.
 */
final class RequestCollectorTest extends TestCase
{
    public function testCaptureReturnsNullBeforeRequestMetadataIsCollected(): void
    {
        $collector = new RequestCollector();

        self::assertNull($collector->capture(), 'Inactive collector must not expose a snapshot.');

        $collector->startup();

        self::assertNull($collector->capture(), 'Collector without request metadata must not expose a snapshot.');
    }

    public function testCaptureReturnsSharedRequestPayloadDuringActiveLifecycle(): void
    {
        $collector = new RequestCollector();
        $request = (new ServerRequest(
            'POST',
            'https://example.test/orders?status=open',
            ['X-Trace' => 'trace-1', 'X-Requested-With' => 'XMLHttpRequest'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        ))
            ->withQueryParams(['status' => 'open'])
            ->withParsedBody(['order' => 42])
            ->withCookieParams(['session' => 'cookie-1']);

        $collector->startup();
        $collector->collectRequest($request);
        $collector->collectResponse(new Response(201, ['X-Result' => 'created']));

        $snapshot = $collector->capture();

        self::assertInstanceOf(
            RequestSnapshot::class,
            $snapshot,
            'Active collector must return the shared request snapshot.',
        );

        $data = RequestSnapshot::fromArray($snapshot->jsonSerialize(), '$.panels.request')->data();

        $general = $data['general'] ?? null;

        self::assertIsArray($general, 'General bucket must be present.');
        self::assertSame('POST', $general['method'] ?? null, 'Captured request method must be preserved.');
        self::assertTrue($general['isAjax'] ?? null, 'XHR marker header must flag the request as AJAX.');
        self::assertTrue($general['isSecureConnection'] ?? null, 'HTTPS scheme must flag a secure connection.');

        $requestHeaders = $data['requestHeaders'] ?? null;

        self::assertIsArray($requestHeaders, 'Request headers must be captured.');
        self::assertSame(
            'trace-1',
            $requestHeaders['X-Trace'] ?? null,
            'Single-value headers must collapse to their scalar.',
        );
        self::assertSame(['status' => 'open'], $data['GET'] ?? null, 'Query parameters must fill the GET bucket.');
        self::assertSame(['order' => 42], $data['POST'] ?? null, 'Parsed body must fill the POST bucket.');
        self::assertSame(
            ['session' => 'cookie-1'],
            $data['COOKIE'] ?? null,
            'Cookie parameters must fill the COOKIE bucket.',
        );
        self::assertSame(
            ['REMOTE_ADDR' => '127.0.0.1'],
            $data['SERVER'] ?? null,
            'Server parameters must fill the SERVER bucket.',
        );
        self::assertSame([], $data['requestBody'] ?? null, 'Empty raw body must collapse the body bucket.');
        self::assertSame(201, $data['statusCode'] ?? null, 'Captured response status must be preserved.');

        $responseHeaders = $data['responseHeaders'] ?? null;

        self::assertIsArray($responseHeaders, 'Response headers must be captured.');
        self::assertSame(
            'created',
            $responseHeaders['X-Result'] ?? null,
            'Response headers must be preserved.',
        );

        $collector->shutdown();

        self::assertNull($collector->capture(), 'Shutdown must clear the active request snapshot.');
    }

    public function testCollectResponseAdoptsTheResolvedRouteNameAndArguments(): void
    {
        $currentRoute = new CurrentRoute();
        $reflection = new ReflectionClass($currentRoute);

        $reflection->getProperty('route')->setValue($currentRoute, Route::get('/orders/{id}')->name('orders/view'));
        $reflection->getProperty('arguments')->setValue($currentRoute, ['id' => '42']);

        $collector = new RequestCollector($currentRoute);

        $collector->startup();
        $collector->collectRequest(new ServerRequest('GET', 'https://example.test/orders/42'));
        $collector->collectResponse(new Response());

        $data = $collector->capture()?->data() ?? [];

        self::assertSame('orders/view', $data['route'] ?? null, 'Resolved route name must fill the route slot.');
        self::assertSame(
            ['id' => '42'],
            $data['actionParams'] ?? null,
            'Route arguments must fill the action parameters.',
        );
    }

    public function testCollectResponseCapturesTheMatchedRouteAndActionDescriptor(): void
    {
        $route = Route::get('/')
            ->name('home')
            ->action(['App\\Web\\HomePage', 'index']);
        $currentRoute = new CurrentRoute();
        $reflection = new ReflectionClass($currentRoute);

        $reflection->getProperty('route')->setValue($currentRoute, $route);
        $reflection->getProperty('arguments')->setValue($currentRoute, ['id' => '7']);

        $collector = new RequestCollector(
            $currentRoute,
            new RouteCollection((new RouteCollector())->addRoute($route)),
        );

        $collector->startup();
        $collector->collectRequest(new ServerRequest('GET', 'https://example.test/'));
        $collector->collectResponse(new Response(200));

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');

        $data = RequestSnapshot::fromArray($snapshot->jsonSerialize(), '$.panels.request')->data();

        self::assertSame('home', $data['route'] ?? null, 'Matched route name must be captured.');
        self::assertSame(
            'App\\Web\\HomePage::index()',
            $data['action'] ?? null,
            'Dispatched action descriptor must be captured.',
        );
        self::assertSame(['id' => '7'], $data['actionParams'] ?? null, 'Route arguments must be captured.');
    }

    public function testThrowLogicExceptionWhenCollectingRequestAfterShutdown(): void
    {
        $collector = new RequestCollector();
        $collector->startup();
        $collector->shutdown();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be started before collecting a request');

        $collector->collectRequest(new ServerRequest('GET', 'https://example.test/'));
    }

    public function testThrowLogicExceptionWhenCollectingRequestBeforeStartup(): void
    {
        $collector = new RequestCollector();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be started before collecting a request');

        $collector->collectRequest(new ServerRequest('GET', 'https://example.test/'));
    }

    public function testThrowLogicExceptionWhenCollectingResponseBeforeStartup(): void
    {
        $collector = new RequestCollector();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must be started before collecting a response');

        $collector->collectResponse(new Response());
    }
}
