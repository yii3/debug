<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Routing;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Yii3\Debug\Routing\RouteDefinitionExtractor;
use Yiisoft\Router\{CurrentRoute, Route, RouteCollection, RouteCollectionInterface, RouteCollector};

/**
 * Unit tests for Yii-native route-definition extraction.
 */
#[Group('request')]
#[Group('routing')]
final class RouteDefinitionExtractorTest extends TestCase
{
    public function testFindReturnsNullForUnavailableRoutesWithoutBreakingCapture(): void
    {
        $routes = new RouteCollection(
            (new RouteCollector())->addRoute(Route::get('/')->name('home')),
        );

        self::assertNull(
            RouteDefinitionExtractor::find('missing', $routes),
            'A missing route must not escape into request collection.',
        );
        self::assertSame(
            [],
            RouteDefinitionExtractor::fromCollection(null),
            'A deliberately unavailable live route collection must remain an empty inventory.',
        );
    }

    public function testFromCollectionIncludesInjectedApplicationMiddleware(): void
    {
        $route = Route::get('/')
            ->name('home')
            ->middleware('App\Middleware\RouteMiddleware')
            ->action('App\Web\HomeAction');

        $collector = (new RouteCollector())
            ->middleware('App\Middleware\ApplicationMiddleware')
            ->addRoute($route);

        $definitions = RouteDefinitionExtractor::fromCollection(new RouteCollection($collector));

        self::assertCount(
            1,
            $definitions,
            'Every registered route must produce one definition.',
        );
        self::assertSame(
            [
                'App\Middleware\ApplicationMiddleware',
                'App\Middleware\RouteMiddleware',
            ],
            $definitions[0]->getMiddlewares(),
            'Collection extraction must describe the effective middleware stack after Yii applies global middleware.',
        );
        self::assertSame(
            'App\Web\HomeAction',
            $definitions[0]->getAction(),
            'Collection extraction must retain the final action separately.',
        );
    }

    public function testFromCollectionPropagatesFailuresForThePanelToSurface(): void
    {
        $routes = self::createStub(RouteCollectionInterface::class);

        $routes
            ->method('getRoutes')
            ->willThrowException(new RuntimeException('Unable to initialize routes.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize routes.');

        RouteDefinitionExtractor::fromCollection($routes);
    }

    public function testFromCurrentRouteBuildsFallbackMatchedMetadata(): void
    {
        $route = Route::post('/articles/{id}')
            ->name('article/update')
            ->host('api.example.test')
            ->action('App\Web\UpdateArticleAction');

        $currentRoute = new CurrentRoute();

        (new ReflectionProperty(CurrentRoute::class, 'route'))->setValue($currentRoute, $route);

        self::assertSame(
            [
                'name' => 'article/update',
                'pattern' => '/articles/{id}',
                'methods' => ['POST'],
                'hosts' => ['api.example.test'],
                'action' => null,
                'middlewares' => null,
            ],
            RouteDefinitionExtractor::fromCurrentRoute($currentRoute)?->toArray(),
            'Public CurrentRoute metadata must remain available without reflecting into the matched Route object.',
        );
    }

    public function testFromCurrentRouteReturnsNullWithoutAMatch(): void
    {
        self::assertNull(
            RouteDefinitionExtractor::fromCurrentRoute(new CurrentRoute()),
            'An unmatched CurrentRoute must not fabricate route metadata.',
        );
    }

    public function testFromRouteSeparatesTheActionFromPrecedingMiddleware(): void
    {
        $route = Route::methods(['GET', 'HEAD'], '/articles/{id}')
            ->name('article/view')
            ->hosts('api.example.test', 'www.example.test')
            ->middleware(
                'App\Middleware\Authentication',
                static fn(): int => 1,
            )
            ->action(['App\Web\ArticleAction', 'run']);

        $definition = RouteDefinitionExtractor::fromRoute($route);

        self::assertSame(
            [
                'name' => 'article/view',
                'pattern' => '/articles/{id}',
                'methods' => ['GET', 'HEAD'],
                'hosts' => ['api.example.test', 'www.example.test'],
                'action' => 'App\Web\ArticleAction::run()',
                'middlewares' => [
                    'App\Middleware\Authentication',
                    'Closure',
                ],
            ],
            $definition->toArray(),
            'Route extraction must retain matching metadata and normalize handlers without retaining objects.',
        );
    }
}
