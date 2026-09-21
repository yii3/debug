<?php

declare(strict_types=1);

namespace Yii3\Debug\Extension;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\CollectorInterface;

use function class_exists;

/**
 * Describes the collector, panel, listener, and definition one optional provider package contributes.
 *
 * Every class name stays a plain `string`, because a provider the host did not install declares names that never
 * resolve; {@see self::installed()} tells the two apart.
 */
final readonly class PackagedProvider
{
    /**
     * @param string $id Stable ID the collector and the panel both report.
     * @param string $collector Collector class the provider package ships.
     * @param string $panel Panel class the provider package ships.
     * @param string $event Event class the collector listens to.
     * @param (Closure(CapturePolicy): CollectorInterface)|null $definition Definition building the collector from the
     * host capture policy, or `null` when the container autowires it.
     */
    public function __construct(
        public string $id,
        public string $collector,
        public string $panel,
        public string $event,
        public Closure|null $definition = null,
    ) {}

    /**
     * Returns whether the provider package is installed, so the debugger wires it.
     *
     * @return bool `true` when the collector class is loadable; `false` when the package is absent.
     */
    public function installed(): bool
    {
        return class_exists($this->collector);
    }
}
