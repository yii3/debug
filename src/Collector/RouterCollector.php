<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Router\RouterSnapshot;
use Yiisoft\Router\{CurrentRoute, RouteCollectionInterface};

/**
 * Captures the matched Yii3 route for the Router panel.
 *
 * Records the matched route name and resolves the dispatched action descriptor from the route collection; the
 * URL-rule trace stays empty because the FastRoute matcher does not emit one.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\RouterCollector($currentRoute, $routes))->capture();
 * ```
 */
final class RouterCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @param CurrentRoute|null $currentRoute Matched-route holder, or `null` when routing information is unavailable.
     * @param RouteCollectionInterface|null $routes Route collection resolving the dispatched action descriptor, or
     * `null` when unavailable.
     */
    public function __construct(
        private readonly CurrentRoute|null $currentRoute = null,
        private readonly RouteCollectionInterface|null $routes = null,
    ) {}

    /**
     * Snapshots the matched route and the dispatched action descriptor.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return RouterSnapshot|null Captured routing payload; `null` when the collector never started.
     */
    public function capture(): RouterSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        $route = $this->currentRoute?->getName() ?? '';

        return RouterSnapshot::capture(RouteActionResolver::resolve($route, $this->routes), [], $route);
    }

    /**
     * Returns the stable ID pairing this collector with the Router panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'router';
    }

    /**
     * Deactivates the collector, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
    }

    /**
     * Activates the collector for the current request cycle.
     */
    public function startup(): void
    {
        $this->active = true;
    }
}
