<?php

declare(strict_types=1);

namespace Yii3\Debug\Routing;

use PHPForge\Debug\Panel\Request\Routing\RouteDefinition;
use Throwable;
use Yiisoft\Router\{CurrentRoute, Route, RouteCollectionInterface};

use function array_pop;
use function array_values;

/**
 * Extracts persistence-safe route definitions from Yii router state.
 *
 * @internal
 */
final class RouteDefinitionExtractor
{
    /**
     * Safely resolves one named route from the collection.
     */
    public static function find(string $name, RouteCollectionInterface|null $routes): RouteDefinition|null
    {
        if ($routes === null) {
            return null;
        }

        try {
            return self::fromRoute($routes->getRoute($name));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Normalizes the current route collection for diagnostic presentation.
     *
     * Collection failures intentionally propagate so presentation code can distinguish them from an empty collection.
     *
     * @return list<RouteDefinition>
     */
    public static function fromCollection(RouteCollectionInterface|null $routes): array
    {
        if ($routes === null) {
            return [];
        }

        $definitions = [];

        foreach ($routes->getRoutes() as $route) {
            $definitions[] = self::fromRoute($route);
        }

        return $definitions;
    }

    /**
     * Builds a partial definition from matched-route metadata when the route collection is unavailable.
     */
    public static function fromCurrentRoute(CurrentRoute $currentRoute): RouteDefinition|null
    {
        $name = $currentRoute->getName();

        if ($name === null) {
            return null;
        }

        $host = $currentRoute->getHost();

        return RouteDefinition::create(name: $name, pattern: $currentRoute->getPattern() ?? '')
            ->withMethods(array_values($currentRoute->getMethods() ?? []))
            ->withHosts($host === null ? [] : [$host]);
    }

    /**
     * Builds a complete definition from a Yii route without retaining handler objects or configuration values.
     */
    public static function fromRoute(Route $route): RouteDefinition
    {
        $handlers = $route->getData('enabledMiddlewares');

        $action = $handlers === [] ? null : HandlerDefinitionNormalizer::describe(array_pop($handlers));

        return RouteDefinition::create(name: $route->getData('name'), pattern: $route->getData('pattern'))
            ->withMethods(array_values($route->getData('methods')))
            ->withHosts(array_values($route->getData('hosts')))
            ->withAction($action)
            ->withMiddlewares(HandlerDefinitionNormalizer::describeAll($handlers));
    }
}
