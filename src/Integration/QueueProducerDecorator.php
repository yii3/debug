<?php

declare(strict_types=1);

namespace Yii3\Debug\Integration;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use Yii3\Debug\Collector\QueueCollector;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\{MessageStatus, QueueProducerInterface};

/**
 * Decorates a Yii3 queue producer and captures messages after the backend enriches them with metadata.
 */
final readonly class QueueProducerDecorator implements QueueProducerInterface
{
    private InstrumentationGuard $guard;

    public function __construct(
        private QueueProducerInterface $decorated,
        private QueueCollector $collector,
        InstrumentationGuard|null $guard = null,
    ) {
        $this->guard = $guard ?? new InstrumentationGuard();
    }

    public function getQueueName(): string
    {
        return $this->decorated->getQueueName();
    }

    public function push(MessageInterface $message): MessageInterface
    {
        $message = $this->decorated->push($message);

        $this->guard->observe(fn() => $this->collector->recordPush($this->decorated, $message));

        return $message;
    }

    public function status(string|int $id): MessageStatus
    {
        return $this->decorated->status($id);
    }
}
