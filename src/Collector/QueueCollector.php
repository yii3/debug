<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueDriverDetector, QueueSnapshot};
use Throwable;
use Yiisoft\Queue\Message\{DelayEnvelope, IdEnvelope, MessageInterface};
use Yiisoft\Queue\QueueProducerInterface;

use function is_int;
use function is_string;
use function microtime;

/**
 * Captures Yii3 queue push and worker lifecycle records submitted by the debug decorators.
 */
final class QueueCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @var list<array<string, mixed>> Queue lifecycle records in event order.
     */
    private array $records = [];

    /**
     * @param list<string> $redactedProperties Exact payload keys redacted before persistence.
     */
    public function __construct(private readonly array $redactedProperties = []) {}

    public function capture(): QueueSnapshot|null
    {
        return $this->active ? QueueSnapshot::capture($this->records) : null;
    }

    public function id(): string
    {
        return 'queue';
    }

    /**
     * Records the result of processing a message in a queue worker.
     */
    public function recordExecution(
        string $queueName,
        MessageInterface $message,
        float $duration,
        Throwable|null $error = null,
    ): void {
        if (!$this->active) {
            return;
        }

        $this->records[] = $this->record(
            $error === null ? JobRecord::TYPE_EXEC : JobRecord::TYPE_ERROR,
            $queueName,
            'Worker',
            '',
            true,
            $message,
            $duration,
            $error,
        );
    }

    /**
     * Records a message returned by a producer after a successful push.
     */
    public function recordPush(QueueProducerInterface $producer, MessageInterface $message): void
    {
        if (!$this->active) {
            return;
        }

        $driverClass = $producer::class;
        [$driverName, $isAsync] = QueueDriverDetector::detect($driverClass);

        $this->records[] = $this->record(
            JobRecord::TYPE_PUSH,
            $producer->getQueueName(),
            $driverName,
            $driverClass,
            $isAsync,
            $message,
            null,
            null,
        );
    }

    public function shutdown(): void
    {
        $this->active = false;
        $this->records = [];
    }

    public function startup(): void
    {
        $this->active = true;
        $this->records = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MessageInterface $message): array
    {
        $redacted = SensitiveDataRedactor::redact(
            ['payload' => $message->getPayload()],
            $this->redactedProperties,
        );

        return ['payload' => $redacted['payload'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        string $eventType,
        string $queueName,
        string $driverName,
        string $driverClass,
        bool $isAsync,
        MessageInterface $message,
        float|null $duration,
        Throwable|null $error,
    ): array {
        $meta = $message->getMeta();

        $id = IdEnvelope::fromMessage($message)->getId();
        $delay = DelayEnvelope::fromMessage($message)->getDelaySeconds();

        return [
            'eventType' => $eventType,
            'componentId' => $queueName,
            'driverName' => $driverName,
            'driverClass' => $driverClass,
            'isAsync' => $isAsync,
            'jobClass' => $message->getType(),
            'payloadFields' => $this->payload($message),
            'time' => microtime(true),
            'jobId' => is_string($id) || is_int($id) ? (string) $id : '',
            'ttr' => is_int($meta['ttr'] ?? null) ? $meta['ttr'] : null,
            'delay' => $delay > 0 ? (int) $delay : null,
            'priority' => is_int($meta['priority'] ?? null) ? $meta['priority'] : null,
            'attempt' => is_int($meta['attempt'] ?? null) ? $meta['attempt'] : null,
            'duration' => $duration,
            'error' => $error?->getMessage() ?? '',
        ];
    }
}
