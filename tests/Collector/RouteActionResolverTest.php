<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;
use Yii3\Debug\Collector\RouteActionResolver;
use Yiisoft\Router\{Route, RouteCollection, RouteCollector};

/**
 * Unit tests for {@see RouteActionResolver} covering descriptor formatting and route-collection resolution.
 *
 * @since 0.1
 */
#[Group('collector')]
final class RouteActionResolverTest extends TestCase
{
    public function testDescribeFormatsSupportedDefinitionShapes(): void
    {
        self::assertSame('App\Web\Action', RouteActionResolver::describe('App\Web\Action'), 'Strings pass through.');
        self::assertSame('Closure', RouteActionResolver::describe(static fn(): int => 1), 'Closures collapse.');
        self::assertSame(
            'App\Controller::view()',
            RouteActionResolver::describe(['App\Controller', 'view']),
            'Callable pairs must format as a method call.',
        );
        self::assertSame(stdClass::class, RouteActionResolver::describe(new stdClass()), 'Objects yield their FQCN.');
        self::assertNull(RouteActionResolver::describe(42), 'Unsupported shapes yield `null`.');
    }

    public function testResolveReadsTheFinalMiddlewareOfTheMatchedRoute(): void
    {
        $route = Route::get('/blog/{id}')
            ->name('blog/view')
            ->action(['App\Controller\BlogController', 'view']);
        $routes = new RouteCollection((new RouteCollector())->addRoute($route));

        self::assertSame(
            'App\Controller\BlogController::view()',
            RouteActionResolver::resolve('blog/view', $routes),
            'Matched route must resolve to its action descriptor.',
        );
    }

    public function testResolveReturnsNullForEmptyRouteMissingCollectionOrUnknownName(): void
    {
        $routes = new RouteCollection((new RouteCollector())->addRoute(Route::get('/')->name('home')));

        self::assertNull(RouteActionResolver::resolve('', $routes), 'An empty route must not resolve.');
        self::assertNull(RouteActionResolver::resolve('home', null), 'A missing collection must not resolve.');
        self::assertNull(RouteActionResolver::resolve('missing', $routes), 'An unknown route must not resolve.');
    }
}
