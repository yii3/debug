<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};

use function microtime;

/**
 * Captures every PSR-14 event dispatched during the request for the Events panel.
 *
 * Receives events from {@see \Yii3\Debug\Event\DebugEventDispatcher}, which the application binds around its real
 * dispatcher; without that binding the capture stays empty and the Events chip is omitted.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\EventCollector())->capture();
 * ```
 */
final class EventCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @var list<EventRow> Events captured for the current request, in dispatch order.
     */
    private array $events = [];

    /**
     * Returns the captured events as a typed snapshot.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return EventSnapshot|null Captured event payload; `null` when the collector never started.
     */
    public function capture(): EventSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        return new EventSnapshot($this->events);
    }

    /**
     * Returns the stable ID pairing this collector with the Events panel.
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
        return 'event';
    }

    /**
     * Records one dispatched PSR-14 event while the collector is active.
     *
     * Usage example:
     *
     * ```php
     * $collector->record($event);
     * ```
     *
     * @param object $event Dispatched event object.
     */
    public function record(object $event): void
    {
        if (!$this->active) {
            return;
        }

        $this->events[] = new EventRow(
            time: microtime(true),
            name: $event::class,
            class: $event::class,
            isStatic: '0',
            senderClass: '',
        );
    }

    /**
     * Deactivates the collector and clears the accumulated events, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
        $this->events = [];
    }

    /**
     * Activates the collector for the current request cycle.
     */
    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;
        $this->events = [];
    }
}
