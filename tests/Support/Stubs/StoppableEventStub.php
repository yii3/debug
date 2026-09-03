<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Event whose propagation can be stopped by a listener stub.
 */
final class StoppableEventStub implements StoppableEventInterface
{
    private bool $stopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }
}
