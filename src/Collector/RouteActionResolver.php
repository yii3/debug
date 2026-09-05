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
     */
    public static function describe(mixed $definition): string|null
    {
        return HandlerDefinitionNormalizer::describe($definition);
    }

    /**
     * Resolves the action descriptor for a matched route name.
     */
    public static function resolve(string $route, RouteCollectionInterface|null $routes): string|null
    {
        if ($route === '') {
            return null;
        }

        return RouteDefinitionExtractor::find($route, $routes)?->getAction();
    }
}
