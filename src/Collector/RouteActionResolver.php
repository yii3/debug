<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Yii3\Debug\Routing\{HandlerDefinitionNormalizer, RouteDefinitionExtractor};
use Yiisoft\Router\RouteCollectionInterface;

/**
 * Resolves a matched route's final middleware definition to a readable action descriptor.
 */
final class RouteActionResolver
{
    /**
     * Formats an action or middleware definition for the Request panel.
     *
     * @param mixed $definition Handler as a class name, a `['class' => ...]` array, a callable pair, or any
     * other value.
     *
     * @return string|null Scalar label, or `null` when the definition carries no recognizable name.
     */
    public static function describe(mixed $definition): string|null
    {
        return HandlerDefinitionNormalizer::describe($definition);
    }

    /**
     * Resolves the action descriptor for a matched route name.
     *
     * @param string $route Matched route name.
     * @param RouteCollectionInterface|null $routes Live route collection, or `null` when it cannot be resolved.
     *
     * @return string|null Action label, or `null` when the route declares none.
     */
    public static function resolve(string $route, RouteCollectionInterface|null $routes): string|null
    {
        if ($route === '') {
            return null;
        }

        return RouteDefinitionExtractor::find($route, $routes)?->getAction();
    }
}
