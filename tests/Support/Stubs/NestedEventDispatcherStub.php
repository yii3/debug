<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Triggers one nested event through the decorated dispatcher.
 */
final class NestedEventDispatcherStub implements EventDispatcherInterface
{
    public EventDispatcherInterface|null $decorator = null;

    /**
     * @var list<object>
     */
    public array $received = [];
    private bool $nested = false;

    public function __construct(private readonly object $nestedEvent) {}

    public function dispatch(object $event): object
    {
        $this->received[] = $event;

        if ($this->nested === false) {
            $this->nested = true;
            $this->decorator?->dispatch($this->nestedEvent);
        }

        return $event;
    }
}
