<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Integration;

use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Collector\QueueCollector;
use Yii3\Debug\Integration\{QueueProducerDecorator, QueueWorkerDecorator};
use Yiisoft\Queue\Message\{GenericMessage, MessageInterface};
use Yiisoft\Queue\{MessageStatus, QueueProducerInterface};
use Yiisoft\Queue\Worker\WorkerInterface;

/**
 * Integration tests for the producer and worker decorators that feed the Queue collector.
 */
#[Group('queue')]
final class QueueDecoratorsTest extends TestCase
{
    public function testProducerDecoratorCapturesBackendEnrichedMessageAndDelegatesStatus(): void
    {
        $collector = new QueueCollector();
        $collector->startup();
        $decorator = new QueueProducerDecorator($this->producer(), $collector);

        $result = $decorator->push(GenericMessage::fromPayload('email', ['id' => 1]));
        $snapshot = $collector->capture();

        self::assertSame('assigned-id', $result->getMeta()['yii-id'] ?? null, 'Decorated producer result must pass through.');
        self::assertSame(MessageStatus::DONE, $decorator->status('assigned-id'), 'Status lookup must delegate unchanged.');
        self::assertSame('jobs', $decorator->getQueueName(), 'Queue name must delegate unchanged.');
        self::assertNotNull($snapshot, 'Producer push must reach the active collector.');
        $record = $snapshot->entries()[0] ?? self::fail('Expected the captured push record.');
        self::assertSame('assigned-id', $record->jobId, 'Enriched ID must be captured after push.');
    }

    public function testWorkerDecoratorCapturesSuccessAndFailureWhilePreservingResults(): void
    {
        $collector = new QueueCollector();
        $collector->startup();
        $message = GenericMessage::fromPayload('report', ['id' => 7]);
        $success = new QueueWorkerDecorator(
            new class implements WorkerInterface {
                public function process(
                    MessageInterface $message,
                    string $queueName,
                    QueueProducerInterface|null $retryProducer = null,
                ): MessageInterface {
                    return $message->withMeta(['attempt' => 1]);
                }
            },
            $collector,
        );

        $result = $success->process($message, 'reports');

        self::assertSame(1, $result->getMeta()['attempt'] ?? null, 'Worker result must pass through unchanged.');

        $failure = new QueueWorkerDecorator(
            new class implements WorkerInterface {
                public function process(
                    MessageInterface $message,
                    string $queueName,
                    QueueProducerInterface|null $retryProducer = null,
                ): MessageInterface {
                    throw new RuntimeException('handler failed');
                }
            },
            $collector,
        );

        try {
            $failure->process($message, 'reports');
            self::fail('Worker failure must be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('handler failed', $exception->getMessage(), 'Original worker exception must survive.');
        }

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Worker events must reach the active collector.');
        $records = $snapshot->entries();
        $successRecord = $records[0] ?? self::fail('Expected the successful worker record.');
        $failureRecord = $records[1] ?? self::fail('Expected the failed worker record.');
        self::assertSame(JobRecord::TYPE_EXEC, $successRecord->eventType, 'Success must record exec.');
        self::assertSame(JobRecord::TYPE_ERROR, $failureRecord->eventType, 'Failure must record error.');
    }

    private function producer(): QueueProducerInterface
    {
        return new class implements QueueProducerInterface {
            public function getQueueName(): string
            {
                return 'jobs';
            }

            public function push(MessageInterface $message): MessageInterface
            {
                return $message->withMeta(['yii-id' => 'assigned-id']);
            }

            public function status(string|int $id): MessageStatus
            {
                return MessageStatus::DONE;
            }
        };
    }
}
