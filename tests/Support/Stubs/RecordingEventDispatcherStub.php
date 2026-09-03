<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Records the dispatched event and returns a configured result object.
 */
final class RecordingEventDispatcherStub implements EventDispatcherInterface
{
    public object|null $received = null;

    public function __construct(private readonly object $result) {}

    public function dispatch(object $event): object
    {
        $this->received = $event;

        return $this->result;
    }
}
