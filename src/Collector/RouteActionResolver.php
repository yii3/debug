<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Closure;
use Throwable;
use Yiisoft\Router\RouteCollectionInterface;

use function array_key_last;
use function is_array;
use function is_object;
use function is_string;

/**
 * Resolves the dispatched action descriptor from a matched route's final middleware definition.
 *
 * Shared by the Request and Router collectors so both panels describe the dispatched action identically.
 */
final class RouteActionResolver
{
    /**
     * Formats a route middleware definition as a readable action descriptor.
     *
     * Usage example:
     * ```php
     * $action = \Yii3\Debug\Collector\RouteActionResolver::describe([Controller::class, 'index']);
     * ```
     *
     * @param mixed $definition Route action or middleware definition.
     *
     * @return string|null Readable descriptor, or `null` for unsupported shapes.
     */
    public static function describe(mixed $definition): string|null
    {
        if (is_string($definition)) {
            return $definition;
        }

        if ($definition instanceof Closure) {
            return 'Closure';
        }

        if (
            is_array($definition)
            && is_string($definition[0] ?? null)
            && is_string($definition[1] ?? null)
        ) {
            return $definition[0] . '::' . $definition[1] . '()';
        }

        if (is_object($definition)) {
            return $definition::class;
        }

        return null;
    }

    /**
     * Resolves the dispatched action descriptor for a matched route name.
     *
     * Usage example:
     * ```php
     * $action = \Yii3\Debug\Collector\RouteActionResolver::resolve('home', $routes);
     * ```
     *
     * @param string $route Matched route name; empty when no route matched.
     * @param RouteCollectionInterface|null $routes Route collection, or `null` when unavailable.
     *
     * @return string|null Readable action descriptor, or `null` when it cannot be resolved.
     */
    public static function resolve(string $route, RouteCollectionInterface|null $routes): string|null
    {
        if ($route === '' || $routes === null) {
            return null;
        }

        try {
            $middlewares = $routes->getRoute($route)->getData('enabledMiddlewares');
        } catch (Throwable) {
            return null;
        }

        if ($middlewares === []) {
            return null;
        }

        return self::describe($middlewares[array_key_last($middlewares)]);
    }
}
