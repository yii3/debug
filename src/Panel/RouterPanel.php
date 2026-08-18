<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Router\{
    ActionRouteRow,
    RouterCurrentView,
    RouterRuleRow,
    RouterSectionRenderer,
    RouterSnapshot,
};
use PHPForge\Debug\Toolbar\ToolbarItem;
use UIAwesome\Html\Heading\H1;
use Yii3\Debug\Collector\RouteActionResolver;
use Yiisoft\Router\RouteCollectionInterface;

use function count;
use function implode;
use function is_string;
use function sprintf;

/**
 * Presents the shared Router panel payload and contributes the matched-route toolbar chip.
 *
 * The Current Route tab is snapshot-driven; the Router Rules and Action Routes tabs are built live from the
 * registered {@see RouteCollectionInterface}, mirroring the Yii2 panel's URL-manager introspection.
 */
final readonly class RouterPanel implements PanelInterface
{
    use PanelContentTrait;

    /**
     * @param RouteCollectionInterface|null $routes Route collection backing the Router Rules and Action Routes tabs,
     * or `null` when unavailable.
     */
    public function __construct(private RouteCollectionInterface|null $routes = null) {}

    public function icon(): string
    {
        return 'router';
    }

    public function id(): string
    {
        return 'router';
    }

    public function name(): string
    {
        return 'Router';
    }

    public function render(array $payload): string
    {
        $current = RouterCurrentView::fromSnapshot(RouterSnapshot::fromArray($payload, 'panels.router'));
        [$ruleRows, $actionRows] = $this->routeRows();

        $routeCount = count($ruleRows);
        $badges = [
            ['label' => 'FastRoute Matcher', 'variant' => 'success'],
            [
                'label' => sprintf('%d %s registered', $routeCount, $routeCount === 1 ? 'route' : 'routes'),
                'variant' => 'muted',
            ],
        ];

        return H1::tag()->class('yii-debug-sr-only')->content('Router')->render()
            . RouterSectionRenderer::renderTabs($current, $ruleRows, $actionRows, $badges);
    }

    public function toolbarItems(array $payload): array
    {
        $route = $payload['route'] ?? null;
        $action = $payload['action'] ?? null;

        if (!is_string($route)) {
            return [];
        }

        return [
            new ToolbarItem(
                value: $route,
                title: 'Action: ' . (is_string($action) ? $action : ''),
            ),
        ];
    }

    /**
     * Builds the Router Rules and Action Routes rows from the registered route collection.
     *
     * @return array{list<RouterRuleRow>, list<ActionRouteRow>} Rule rows and action rows in registration order.
     */
    private function routeRows(): array
    {
        $ruleRows = [];
        $actionRows = [];

        foreach ($this->routes?->getRoutes() ?? [] as $route) {
            $name = $route->getData('name');
            $pattern = $route->getData('pattern');
            $action = RouteActionResolver::resolve($name, $this->routes);

            $ruleRows[] = new RouterRuleRow(
                name: $name,
                route: $pattern,
                verb: implode(', ', $route->getData('methods')),
                suffix: $route->getData('host') ?? '',
                mode: '',
                type: $action ?? '',
            );

            if ($action !== null) {
                $actionRows[] = new ActionRouteRow(
                    action: $action,
                    route: $name,
                    rule: $pattern,
                    count: 0,
                );
            }
        }

        return [$ruleRows, $actionRows];
    }
}
