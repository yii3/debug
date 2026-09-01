<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use stdClass;
use Yii3\Debug\Collector\RouteActionResolver;
use Yiisoft\Router\{Route, RouteCollection, RouteCollector};

/**
 * Unit tests for Request-panel route action resolution.
 */
#[Group('collector')]
#[Group('request')]
final class RouteActionResolverTest extends TestCase
{
    public function testDescribeFormatsSupportedDefinitionShapes(): void
    {
        $controller = new stdClass();

        self::assertSame(
            'App\Web\Action',
            RouteActionResolver::describe('App\Web\Action'),
            'String definitions must pass through.',
        );
        self::assertSame(
            'Closure',
            RouteActionResolver::describe(static fn(): int => 1),
            'Closures must use a stable label.',
        );
        self::assertSame(
            'App\Controller::view()',
            RouteActionResolver::describe(['App\Controller', 'view']),
            'Class-method definitions must use method-call notation.',
        );
        self::assertSame(
            stdClass::class . '::run()',
            RouteActionResolver::describe([$controller, 'run']),
            'Object-method definitions must use the object class.',
        );
        self::assertSame(
            ConfiguredMiddleware::class,
            RouteActionResolver::describe(
                [
                    'class' => ConfiguredMiddleware::class,
                    '__construct()' => [],
                ],
            ),
            'Associative middleware definitions must expose their configured class.',
        );
        self::assertSame(
            stdClass::class,
            RouteActionResolver::describe($controller),
            'Invokable object definitions must expose their class.',
        );
    }

    public function testDescribeReturnsNullForUnsupportedDefinitions(): void
    {
        self::assertNull(
            RouteActionResolver::describe(42),
            'Scalar definitions other than strings must not be fabricated.',
        );
        self::assertNull(
            RouteActionResolver::describe(['App\Controller']),
            'Incomplete array definitions must not be fabricated.',
        );
        self::assertNull(
            RouteActionResolver::describe([42, 'run']),
            'Definitions with unsupported targets must not be fabricated.',
        );
        self::assertNull(
            RouteActionResolver::describe(['class' => 42]),
            'Definitions with a non-string configured class must not be fabricated.',
        );
    }

    public function testResolveDescribesAnAssociativeActionDefinition(): void
    {
        $route = Route::get('/configured')
            ->name('configured')
            ->action(
                [
                    'class' => ConfiguredMiddleware::class,
                    '__construct()' => [],
                ],
            );

        $routes = new RouteCollection((new RouteCollector())->addRoute($route));

        self::assertSame(
            ConfiguredMiddleware::class,
            RouteActionResolver::resolve('configured', $routes),
            'A valid middleware array definition must resolve to its configured class.',
        );
    }

    public function testResolveReadsTheFinalMiddlewareOfTheMatchedRoute(): void
    {
        $route = Route::get('/blog/{id}')
            ->name('blog/view')
            ->middleware('App\Middleware\Authentication')
            ->action(
                [
                    'App\Controller\BlogController',
                    'view',
                ],
            );

        $routes = new RouteCollection((new RouteCollector())->addRoute($route));

        self::assertSame(
            'App\Controller\BlogController::view()',
            RouteActionResolver::resolve('blog/view', $routes),
            'The route action, rather than a preceding middleware, must be resolved.',
        );
    }

    public function testResolveReturnsNullWithoutAResolvableAction(): void
    {
        $routes = new RouteCollection(
            (new RouteCollector())
                ->addRoute(Route::get('/')->name('home')),
        );

        self::assertNull(
            RouteActionResolver::resolve('', $routes),
            'An empty route name must not resolve.',
        );
        self::assertNull(
            RouteActionResolver::resolve('home', null),
            'A missing route collection must not resolve.',
        );
        self::assertNull(
            RouteActionResolver::resolve('missing', $routes),
            'An unknown route name must not escape the collector.',
        );
        self::assertNull(
            RouteActionResolver::resolve('home', $routes),
            'A route without an action must not fabricate a descriptor.',
        );
    }
}

final class ConfiguredMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
