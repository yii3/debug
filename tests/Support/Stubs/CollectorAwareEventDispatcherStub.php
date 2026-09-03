<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\EventCollector;

/**
 * Reports whether event metadata was collected before delegation.
 */
final class CollectorAwareEventDispatcherStub implements EventDispatcherInterface
{
    public object|null $received = null;
    public bool $wasRecordedBeforeDelegation = false;

    public function __construct(private readonly EventCollector $collector, private readonly object $result) {}

    public function dispatch(object $event): object
    {
        $this->received = $event;

        $snapshot = $this->collector->capture();

        $this->wasRecordedBeforeDelegation = $snapshot !== null
            && ($snapshot->entries()[0]->class ?? null) === $event::class;

        return $this->result;
    }
}
