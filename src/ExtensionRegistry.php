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
        $first = $builtIn;
        $collectors = [];
        $overridden = false;

        foreach ($this->collectors as $collector) {
            if (!$overridden && $collector->id() === $builtIn->id()) {
                $first = $collector;
                $overridden = true;

                continue;
            }

            $collectors[] = $collector;
        }

        return [$first, ...$collectors];
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
        $first = $builtIn;
        $panels = [];
        $overridden = false;

        foreach ($this->panels as $panel) {
            if (!$overridden && $panel->id() === $builtIn->id()) {
                $first = $panel;
                $overridden = true;

                continue;
            }

            $panels[] = $panel;
        }

        return [$first, ...$panels];
    }

    public function withCollector(CollectorInterface $collector): self
    {
        return new self([...$this->collectors, $collector], $this->panels);
    }

    public function withPanel(ExtensionPanelInterface $panel): self
    {
        return new self($this->collectors, [...$this->panels, $panel]);
    }
}
