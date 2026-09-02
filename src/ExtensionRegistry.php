<?php

declare(strict_types=1);

namespace Yii3\Debug;

use PHPForge\Debug\Collector\CollectorInterface;
use Yii3\Debug\Panel\ExtensionPanelInterface;

/**
 * Holds the collectors and panels explicitly enabled by the application.
 */
final readonly class ExtensionRegistry
{
    /**
     * @var list<CollectorInterface>
     */
    private array $collectors;
    /**
     * @var list<ExtensionPanelInterface>
     */
    private array $panels;

    /**
     * @param iterable<CollectorInterface> $collectors Enabled collectors in capture order.
     * @param iterable<ExtensionPanelInterface> $panels Enabled panels in navigation order.
     */
    public function __construct(iterable $collectors = [], iterable $panels = [])
    {
        $collectorList = [];

        foreach ($collectors as $collector) {
            $collectorList[] = $collector;
        }

        $panelList = [];

        foreach ($panels as $panel) {
            $panelList[] = $panel;
        }

        $this->collectors = $collectorList;
        $this->panels = $panelList;
    }

    /**
     * @return list<CollectorInterface> Enabled collectors in capture order.
     */
    public function collectors(): array
    {
        return $this->collectors;
    }

    /**
     * Returns the built-in collector first, unless the application explicitly registered an override with the same ID.
     *
     * @return list<CollectorInterface> Built-in and enabled collectors in capture order.
     */
    public function collectorsWithBuiltIn(CollectorInterface $builtIn): array
    {
        return $this->collectorsWithBuiltIns([$builtIn]);
    }

    /**
     * Returns built-in collectors first, replacing each one with an explicitly registered collector of the same ID.
     *
     * @param iterable<CollectorInterface> $builtIns Built-in collectors in capture order.
     *
     * @return list<CollectorInterface> Built-in and enabled collectors in capture order.
     */
    public function collectorsWithBuiltIns(iterable $builtIns): array
    {
        $collectors = $this->collectors;

        $resolved = [];

        foreach ($builtIns as $builtIn) {
            $resolvedBuiltIn = $builtIn;

            foreach ($collectors as $index => $collector) {
                if ($collector->id() !== $builtIn->id()) {
                    continue;
                }

                $resolvedBuiltIn = $collector;

                unset($collectors[$index]);

                break;
            }

            $resolved[] = $resolvedBuiltIn;
        }

        return [
            ...$resolved,
            ...$collectors,
        ];
    }

    /**
     * Creates a registry from explicitly enabled collectors and panels.
     *
     * @param iterable<CollectorInterface> $collectors Enabled collectors in capture order.
     * @param iterable<ExtensionPanelInterface> $panels Enabled panels in navigation order.
     */
    public static function create(iterable $collectors = [], iterable $panels = []): self
    {
        return new self(
            $collectors,
            $panels,
        );
    }

    /**
     * @return list<ExtensionPanelInterface> Enabled panels in navigation order.
     */
    public function panels(): array
    {
        return $this->panels;
    }

    /**
     * Returns the built-in panel first, unless the application explicitly registered an override with the same ID.
     *
     * @return list<ExtensionPanelInterface> Built-in and enabled panels in navigation order.
     */
    public function panelsWithBuiltIn(ExtensionPanelInterface $builtIn): array
    {
        return $this->panelsWithBuiltIns([$builtIn]);
    }

    /**
     * Returns built-in panels first, replacing each one with an explicitly registered panel of the same ID.
     *
     * @param iterable<ExtensionPanelInterface> $builtIns Built-in panels in navigation order.
     *
     * @return list<ExtensionPanelInterface> Built-in and enabled panels in navigation order.
     */
    public function panelsWithBuiltIns(iterable $builtIns): array
    {
        $panels = $this->panels;

        $resolved = [];

        foreach ($builtIns as $builtIn) {
            $resolvedBuiltIn = $builtIn;

            foreach ($panels as $index => $panel) {
                if ($panel->id() !== $builtIn->id()) {
                    continue;
                }

                $resolvedBuiltIn = $panel;

                unset($panels[$index]);

                break;
            }

            $resolved[] = $resolvedBuiltIn;
        }

        return [
            ...$resolved,
            ...$panels,
        ];
    }

    public function withCollector(CollectorInterface $collector): self
    {
        return new self(
            [
                ...$this->collectors,
                $collector,
            ],
            $this->panels,
        );
    }

    public function withPanel(ExtensionPanelInterface $panel): self
    {
        return new self(
            $this->collectors,
            [
                ...$this->panels,
                $panel,
            ],
        );
    }
}
