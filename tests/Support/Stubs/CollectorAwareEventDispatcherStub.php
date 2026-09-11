<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Tests\Support\Captured;

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

        $snapshot = Captured::event($this->collector);

        $this->wasRecordedBeforeDelegation = $snapshot !== null
            && ($snapshot->entries()[0]->class ?? null) === $event::class;

        return $this->result;
    }
}
