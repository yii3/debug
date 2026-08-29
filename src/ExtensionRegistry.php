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
     * @return list<ExtensionPanelInterface> Enabled panels in navigation order.
     */
    public function panels(): array
    {
        return $this->panels;
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
