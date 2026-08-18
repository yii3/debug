<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\RouterPanel;
use Yiisoft\Router\{Route, RouteCollection, RouteCollector};

/**
 * Unit tests for {@see RouterPanel} presenting the shared Router payload and its matched-route chip.
 */
final class RouterPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheRouterPanel(): void
    {
        $panel = new RouterPanel();

        self::assertSame('router', $panel->id(), 'Stable ID must pair with the router collector.');
        self::assertSame('router', $panel->icon(), 'Icon must use the shared router glyph.');
        self::assertSame('Router', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderListsRegisteredRoutesAndActionRoutes(): void
    {
        $route = Route::get('/blog/{id}')
            ->name('blog/view')
            ->action(['App\Controller\BlogController', 'view']);
        $routes = new RouteCollection((new RouteCollector())->addRoute($route));

        $html = (new RouterPanel($routes))->render(
            ['action' => null, 'route' => '', 'message' => null, 'entries' => []],
        );

        self::assertStringContainsString('/blog/{id}', $html, 'Route pattern must surface in the rules table.');
        self::assertStringContainsString(
            'App\Controller\BlogController::view()',
            $html,
            'Action descriptor must surface in the rules and action tables.',
        );
        self::assertStringContainsString('1 route registered', $html, 'Flags strip must count registered routes.');
        self::assertStringContainsString('GET', $html, 'Route verbs must surface in the rules table.');
    }

    public function testRenderShowsCurrentRouteSummaryAndTabs(): void
    {
        $payload = [
            'action' => 'App\Controller\BlogController::view()',
            'route' => 'blog/view',
            'message' => null,
            'entries' => [],
        ];

        $html = (new RouterPanel())->render($payload);

        self::assertStringContainsString('blog/view', $html, 'Resolved route must surface in the summary.');
        self::assertStringContainsString('BlogController::view()', $html, 'Dispatched action must surface.');
        self::assertStringContainsString('Current Route', $html, 'Current Route tab must render.');
        self::assertStringContainsString('Router Rules', $html, 'Router Rules tab must render.');
        self::assertStringContainsString('Action Routes', $html, 'Action Routes tab must render.');
        self::assertStringContainsString('FastRoute Matcher', $html, 'Flags strip must name the matcher.');
        self::assertStringContainsString('No routing rules configured.', $html, 'Missing collection must show the empty rules state.');
    }

    public function testToolbarItemsExposeMatchedRouteChip(): void
    {
        $items = (new RouterPanel())->toolbarItems(
            ['action' => 'App\Controller\SiteController::index()', 'route' => 'home', 'entries' => []],
        );

        self::assertCount(1, $items, 'Exactly one route chip must be emitted.');
        self::assertSame('home', $items[0]->value, 'Chip value must expose the route name.');
        self::assertSame(
            'Action: App\Controller\SiteController::index()',
            $items[0]->title,
            'Tooltip must describe the action.',
        );
    }

    public function testToolbarItemsStayEmptyWithoutRouteString(): void
    {
        self::assertSame([], (new RouterPanel())->toolbarItems([]), 'Missing route must not emit a chip.');
    }
}
