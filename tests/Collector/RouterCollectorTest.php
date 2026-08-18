<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yii3\Debug\Collector\RouterCollector;
use Yiisoft\Router\{CurrentRoute, Route, RouteCollection, RouteCollector};

/**
 * Unit tests for {@see RouterCollector} capturing the matched Yii3 route into the shared Router payload.
 */
final class RouterCollectorTest extends TestCase
{
    public function testCaptureReportsEmptyRouteWithoutRoutingInformation(): void
    {
        $collector = new RouterCollector();

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertSame('', $snapshot->route, 'Unresolved routing must yield an empty route.');
        self::assertNull($snapshot->action, 'Unresolved routing must yield no action descriptor.');
    }

    public function testCaptureResolvesRouteNameAndActionDescriptorDuringActiveLifecycle(): void
    {
        $route = Route::get('/blog/{id}')
            ->name('blog/view')
            ->action(['App\Controller\BlogController', 'view']);
        $currentRoute = new CurrentRoute();
        $reflection = new ReflectionClass($currentRoute);

        $reflection->getProperty('route')->setValue($currentRoute, $route);
        $reflection->getProperty('arguments')->setValue($currentRoute, ['id' => '7']);

        $collector = new RouterCollector($currentRoute, $this->routes($route));

        $collector->startup();
        $snapshot = $collector->capture();
        $collector->shutdown();

        self::assertNotNull($snapshot, 'Active collector must expose a snapshot.');
        self::assertSame('blog/view', $snapshot->route, 'Matched route name must be captured.');
        self::assertSame(
            'App\Controller\BlogController::view()',
            $snapshot->action,
            'Action descriptor must name the controller method.',
        );
        self::assertSame([], $snapshot->entries(), 'FastRoute matching must not produce a rule trace.');
        self::assertNull($collector->capture(), 'Collector must stop exposing data after shutdown.');
    }
    public function testCaptureReturnsNullWhenCollectorNeverStarted(): void
    {
        self::assertNull((new RouterCollector())->capture(), 'Inactive collector must not expose a snapshot.');
    }

    /**
     * Creates a route collection holding the given routes.
     *
     * @param Route ...$routes Routes to register.
     *
     * @return RouteCollection Configured collection.
     */
    private function routes(Route ...$routes): RouteCollection
    {
        return new RouteCollection((new RouteCollector())->addRoute(...$routes));
    }
}
