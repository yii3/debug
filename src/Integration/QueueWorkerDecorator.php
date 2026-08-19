<?php

declare(strict_types=1);

namespace Yii3\Debug\Integration;

use Throwable;
use Yii3\Debug\Collector\QueueCollector;
use Yiisoft\Queue\Message\MessageInterface;
use Yiisoft\Queue\QueueProducerInterface;
use Yiisoft\Queue\Worker\WorkerInterface;

use function microtime;

/**
 * Decorates a Yii3 queue worker and records successful and failed executions with their durations.
 */
final readonly class QueueWorkerDecorator implements WorkerInterface
{
    public function __construct(
        private WorkerInterface $decorated,
        private QueueCollector $collector,
    ) {}

    public function process(
        MessageInterface $message,
        string $queueName,
        QueueProducerInterface|null $retryProducer = null,
    ): MessageInterface {
        $start = microtime(true);

        try {
            $result = $this->decorated->process($message, $queueName, $retryProducer);
        } catch (Throwable $throwable) {
            $this->collector->recordExecution($queueName, $message, microtime(true) - $start, $throwable);

            throw $throwable;
        }

        $this->collector->recordExecution($queueName, $result, microtime(true) - $start);

        return $result;
    }
}
