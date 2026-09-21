<?php

declare(strict_types=1);

namespace Yii3\Debug\Extension;

use Closure;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\CollectorInterface;

use function array_values;

/**
 * Lists the optional providers the debugger wires on its own, each one as soon as its package is installed; an
 * application drops one through the `enabled` option of its `collectors` and `panels` entries.
 */
final readonly class ProviderCatalog
{
    /**
     * @var list<PackagedProvider> Providers in registration order.
     */
    private array $providers;

    /**
     * @param PackagedProvider ...$providers Providers in registration order.
     */
    public function __construct(PackagedProvider ...$providers)
    {
        $this->providers = array_values($providers);
    }

    /**
     * Returns the collector classes of every installed provider, keyed by stable ID.
     *
     * @return array<string, string> Collector classes indexed by provider ID.
     */
    public function collectors(): array
    {
        $collectors = [];

        foreach ($this->installed() as $provider) {
            $collectors[$provider->id] = $provider->collector;
        }

        return $collectors;
    }

    /**
     * Returns the container definitions of every installed provider declaring one, keyed by collector class.
     *
     * @return array<string, Closure(CapturePolicy): CollectorInterface> Definitions indexed by collector class.
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->installed() as $provider) {
            if ($provider->definition !== null) {
                $definitions[$provider->collector] = $provider->definition;
            }
        }

        return $definitions;
    }

    /**
     * Returns the listener registration of every installed provider, keyed by the event the collector observes.
     *
     * @return array<string, list<string>> Collector classes indexed by event class.
     */
    public function listeners(): array
    {
        $listeners = [];

        foreach ($this->installed() as $provider) {
            $listeners[$provider->event] = [$provider->collector];
        }

        return $listeners;
    }

    /**
     * Returns the catalog of providers this package ships, in registration order.
     *
     * Provider classes are written as plain strings, so the package neither imports nor requires the optional
     * packages shipping them.
     *
     * @return self Catalog holding the Inertia provider first and the Vite provider second.
     */
    public static function packaged(): self
    {
        $inertia = 'PHPForge\Inertia\Debug\InertiaCollector';

        return new self(
            new PackagedProvider(
                'inertia',
                $inertia,
                'PHPForge\Inertia\Debug\InertiaPanel',
                'PHPForge\Inertia\Event\ProtocolResultCreated',
                // The packaged Inertia collector redacts page props and URLs with the same policy the Request panel
                // applies.
                static fn(CapturePolicy $capturePolicy): CollectorInterface => new $inertia(
                    $capturePolicy->redact(...),
                    $capturePolicy->redactUrl(...),
                ),
            ),
            new PackagedProvider(
                'vite',
                'PHPForge\Vite\Debug\ViteCollector',
                'PHPForge\Vite\Debug\VitePanel',
                'PHPForge\Vite\Event\AssetsResolved',
            ),
        );
    }

    /**
     * Returns the panel classes of every installed provider, keyed by stable ID.
     *
     * @return array<string, string> Panel classes indexed by provider ID.
     */
    public function panels(): array
    {
        $panels = [];

        foreach ($this->installed() as $provider) {
            $panels[$provider->id] = $provider->panel;
        }

        return $panels;
    }

    /**
     * Filters the catalog down to the providers whose package the application installed.
     *
     * @return list<PackagedProvider> Installed providers in registration order.
     */
    private function installed(): array
    {
        $installed = [];

        foreach ($this->providers as $provider) {
            if ($provider->installed()) {
                $installed[] = $provider;
            }
        }

        return $installed;
    }
}
