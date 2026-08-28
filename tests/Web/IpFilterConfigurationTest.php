<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use Closure;
use GuzzleHttp\Psr7\{HttpFactory, Response, ServerRequest};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Router\Group as RouteGroup;
use Yiisoft\Validator\Validator;
use Yiisoft\Yii\Middleware\IpFilter;

/**
 * Integration tests for the package-owned Yii IP filter configuration.
 */
#[Group('toolbar')]
final class IpFilterConfigurationTest extends TestCase
{
    public function testDefaultDefinitionAllowsLoopbackAndRejectsUnknownClients(): void
    {
        $params = require dirname(__DIR__, 2) . '/config/params.php';
        self::assertIsArray($params, 'Package parameters must return an array.');

        $allowed = $this->process(
            $params,
            new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '127.0.0.1']),
        );
        $denied = $this->process(
            $params,
            new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '203.0.113.10']),
        );

        self::assertSame(204, $allowed->getStatusCode(), 'Loopback client must reach the protected toolbar action.');
        self::assertSame(403, $denied->getStatusCode(), 'Unknown client must be rejected before the toolbar action.');
        self::assertSame('Forbidden', (string) $denied->getBody(), 'Yii IpFilter must return an explicit rejection.');
    }

    public function testDefinitionSupportsConfiguredIpRanges(): void
    {
        $params = require dirname(__DIR__, 2) . '/config/params.php';
        self::assertIsArray($params, 'Package parameters must return an array.');
        $debug = $params['yii3/debug'] ?? null;
        self::assertIsArray($debug, 'Package debug parameters must be present.');
        $debug['allowedIPs'] = ['10.0.0.0/8'];
        $params['yii3/debug'] = $debug;

        $allowed = new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '10.25.0.8']);
        $denied = new ServerRequest('GET', '/debug/toolbar', serverParams: ['REMOTE_ADDR' => '203.0.113.10']);

        self::assertSame(204, $this->process($params, $allowed)->getStatusCode(), 'Configured CIDR must be allowed.');
        self::assertSame(403, $this->process($params, $denied)->getStatusCode(), 'Address outside the CIDR must fail.');
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
    }

    /**
     * @param array<mixed> $params Package parameters.
     */
    private function process(array $params, ServerRequestInterface $request): ResponseInterface
    {
        $routes = require dirname(__DIR__, 2) . '/config/routes.php';
        self::assertIsArray($routes, 'Route configuration must return an array.');
        $group = $routes[0] ?? null;
        self::assertInstanceOf(RouteGroup::class, $group, 'Debugger routes must share a protected group.');
        $middlewares = $group->getData('enabledMiddlewares');
        $filterFactory = $middlewares[0] ?? null;
        self::assertInstanceOf(Closure::class, $filterFactory, 'The route group must expose its IpFilter factory.');
        $filter = $filterFactory(new HttpFactory(), new Validator());
        self::assertInstanceOf(IpFilter::class, $filter, 'Route configuration must build Yii IpFilter.');

        return $filter->process($request, $this->handler());
    }
}
