<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use Closure;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yiisoft\Router\{Group as RouteGroup, RouteCollection, RouteCollector};

/**
 * Unit tests for the toolbar route configuration.
 */
#[Group('toolbar')]
final class RouteConfigTest extends TestCase
{
    public function testOnlyToolbarDataRouteIsPublished(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';
        self::assertIsArray($params, 'Parameter configuration must return an array.');
        $debug = $params['yii3/debug'] ?? null;
        self::assertIsArray($debug, 'Debug parameters must be present.');
        $debug['routePrefix'] = '/developer/debug';
        $params['yii3/debug'] = $debug;
        $routes = require dirname(__DIR__) . '/config/routes.php';

        self::assertIsArray($routes, 'Route configuration must return an array.');
        self::assertCount(1, $routes, 'Debugger routes must share one protected group.');
        $group = $routes[0] ?? null;
        self::assertInstanceOf(RouteGroup::class, $group);
        self::assertSame('/developer/debug', $group->getData('prefix'));
        $middlewares = $group->getData('enabledMiddlewares');
        self::assertCount(1, $middlewares, 'The route must use Yii IpFilter.');
        self::assertInstanceOf(Closure::class, $middlewares[0]);

        $collector = new RouteCollector();
        $collector->addRoute($group);
        $flattened = array_values((new RouteCollection($collector))->getRoutes());

        self::assertCount(1, $flattened, 'Only toolbar data must be routed.');
        self::assertSame('/developer/debug/toolbar', $flattened[0]->getData('pattern'));
        self::assertSame(['GET'], $flattened[0]->getData('methods'));
    }
    public function testPackagePublishesToolbarMiddleware(): void
    {
        $params = require dirname(__DIR__) . '/config/params.php';
        self::assertIsArray($params, 'Parameter configuration must return an array.');
        $dispatcher = $params['yiisoft/middleware-dispatcher'] ?? null;
        self::assertIsArray($dispatcher, 'Middleware dispatcher parameters must be present.');

        self::assertSame([ToolbarMiddleware::class], $dispatcher['middlewares'] ?? null);
    }
}
