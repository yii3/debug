<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Routing;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Routing\RequestRoutingViewFactory;
use Yiisoft\Router\{Route, RouteCollection, RouteCollector};

/**
 * Unit tests for adapting captured Yii request routing state to the shared Request view.
 */
#[Group('request')]
#[Group('routing')]
final class RequestRoutingViewFactoryTest extends TestCase
{
    public function testInventoryOmitsDebuggerRoutesWithoutFilteringApplicationPaths(): void
    {
        $collector = (new RouteCollector())
            ->addRoute(
                Route::get('/diagnostics')->name('yii3-debug/history'),
                Route::get('/diagnostics/view')->name('yii3-debug/config'),
                Route::get('/debug/tutorial')->name('help/debug'),
                Route::get('/debugging')->name('yii3-debugger'),
                Route::get('/')->name('home'),
            );
        $routes = new RouteCollection($collector);

        $routing = RequestRoutingViewFactory::fromRequestData([], $routes);

        self::assertNotNull(
            $routing->inventory,
            'The application inventory must remain available.',
        );
        self::assertSame(
            ['help/debug', 'yii3-debugger', 'home'],
            array_map(static fn($route): string => $route->getName(), $routing->inventory->getRoutes()),
            'Only the reserved debugger namespace must be omitted.',
        );
        self::assertCount(
            5,
            $routes->getRoutes(),
            'The router collection must not be mutated.',
        );
    }

    public function testRouteParametersPreserveOnlyCapturedArrayShapes(): void
    {
        $captured = RequestRoutingViewFactory::fromRequestData(
            ['actionParams' => ['id' => '42']],
            null,
        );
        $malformed = RequestRoutingViewFactory::fromRequestData(
            ['actionParams' => 'id=42'],
            null,
        );

        self::assertSame(
            ['id' => '42'],
            $captured->current->getParameters(),
            'Captured route parameters must remain available to the Input tab.',
        );
        self::assertSame(
            [],
            $malformed->current->getParameters(),
            'Malformed historical route parameters must not leak into the typed Request view.',
        );
    }
}
