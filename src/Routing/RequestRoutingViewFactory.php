<?php

declare(strict_types=1);

namespace Yii3\Debug\Routing;

use PHPForge\Debug\Panel\Request\Routing\{CurrentRouteView, RequestRoutingView, RouteDefinition, RouteInventoryView};
use Throwable;
use Yii3\Debug\Exception\Message;
use Yiisoft\Router\RouteCollectionInterface;

use function array_filter;
use function array_key_exists;
use function array_values;
use function is_array;
use function is_string;
use function str_starts_with;

/**
 * Adapts captured Yii routing state and the live route collection to the framework-neutral Request view.
 */
final class RequestRoutingViewFactory
{
    /**
     * Composes the Request routing view from the captured payload and the live route collection.
     *
     * @param array<array-key, mixed> $data Captured Request panel data.
     * @param RouteCollectionInterface|null $routes Live route collection, or `null` when it cannot be resolved.
     *
     * @return RequestRoutingView Composed view of the current route and the application route inventory.
     */
    public static function fromRequestData(array $data, RouteCollectionInterface|null $routes): RequestRoutingView
    {
        [$definition, $definitionError] = self::capturedDefinition($data);

        $route = is_string($data['route'] ?? null) ? $data['route'] : '';
        $action = is_string($data['action'] ?? null) ? $data['action'] : null;

        if ($route === '') {
            $route = $definition === null ? '' : $definition->getName();
        }

        if ($action === null || $action === '') {
            $action = $definition?->getAction();
        }

        $current = CurrentRouteView::create(route: $route)
            ->withAction($action)
            ->withParameters(is_array($data['actionParams'] ?? null) ? $data['actionParams'] : [])
            ->withDefinition($definition)
            ->withError($definitionError);

        return new RequestRoutingView(current: $current, inventory: self::inventory($routes));
    }

    /**
     * Rebuilds the route definition stored in the capture, reporting why it could not be read.
     *
     * @param array<array-key, mixed> $data Captured Request panel data.
     *
     * @return array{RouteDefinition|null, string|null} Definition and the failure message, either of which may be
     * `null`.
     */
    private static function capturedDefinition(array $data): array
    {
        if (!array_key_exists('routeDefinition', $data) || $data['routeDefinition'] === null) {
            return [
                null,
                null,
            ];
        }

        if (!is_array($data['routeDefinition'])) {
            return [
                null,
                Message::ROUTE_METADATA_INVALID->value,
            ];
        }

        try {
            return [RouteDefinition::fromArray($data['routeDefinition']), null];
        } catch (Throwable $throwable) {
            return [
                null,
                'Captured route metadata could not be read: ' . $throwable->getMessage(),
            ];
        }
    }

    /**
     * Builds the route inventory, omitting it when the collection is unavailable or cannot be read.
     *
     * @param RouteCollectionInterface|null $routes Live route collection, or `null` when it cannot be resolved.
     *
     * @return RouteInventoryView|null Inventory of declared routes, or `null` when none can be listed.
     */
    private static function inventory(RouteCollectionInterface|null $routes): RouteInventoryView|null
    {
        if ($routes === null) {
            return null;
        }

        try {
            $definitions = array_values(
                array_filter(
                    RouteDefinitionExtractor::fromCollection($routes),
                    static fn(RouteDefinition $route): bool => !str_starts_with($route->getName(), 'yii3-debug/'),
                )
            );

            $error = null;
        } catch (Throwable $throwable) {
            $definitions = [];
            $error = 'Current route configuration could not be read: '
                . $throwable::class
                . ': '
                . $throwable->getMessage();
        }

        return RouteInventoryView::create(routes: $definitions)->withError($error);
    }
}
