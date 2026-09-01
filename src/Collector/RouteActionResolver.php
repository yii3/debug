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
 * Resolves a matched route's final middleware definition to a readable action descriptor.
 */
final class RouteActionResolver
{
    /**
     * Formats an action or middleware definition for the Request panel.
     */
    public static function describe(mixed $definition): string|null
    {
        if (is_string($definition)) {
            return $definition;
        }

        if ($definition instanceof Closure) {
            return 'Closure';
        }

        if (is_array($definition) && is_string($definition['class'] ?? null)) {
            return $definition['class'];
        }

        if (
            is_array($definition)
            && (is_string($definition[0] ?? null) || is_object($definition[0] ?? null))
            && is_string($definition[1] ?? null)
        ) {
            $class = is_object($definition[0]) ? $definition[0]::class : $definition[0];

            return $class . '::' . $definition[1] . '()';
        }

        if (is_object($definition)) {
            return $definition::class;
        }

        return null;
    }

    /**
     * Resolves the action descriptor for a matched route name.
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
