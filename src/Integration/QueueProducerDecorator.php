<?php

declare(strict_types=1);

namespace Yii3\Debug\Integration;

use Yii3\Debug\Collector\QueueCollector;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\{MessageStatus, QueueProducerInterface};

/**
 * Decorates a Yii3 queue producer and captures messages after the backend enriches them with metadata.
 */
final readonly class QueueProducerDecorator implements QueueProducerInterface
{
    public function __construct(
        private QueueProducerInterface $decorated,
        private QueueCollector $collector,
    ) {}

    public function getQueueName(): string
    {
        return $this->decorated->getQueueName();
    }

    public function push(MessageInterface $message): MessageInterface
    {
        $message = $this->decorated->push($message);

        $this->collector->recordPush($this->decorated, $message);

        return $message;
    }

    public function status(string|int $id): MessageStatus
    {
        return $this->decorated->status($id);
    }
}
