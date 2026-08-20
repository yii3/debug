<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Integration;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
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

    public function testProducerDecoratorPreservesExactResultWhenCollectorFails(): void
    {
        $collector = new QueueCollector();
        $collector->startup();
        $message = GenericMessage::fromPayload('email', ['id' => 1]);
        $reported = null;
        $producer = new class implements QueueProducerInterface {
            public function getQueueName(): string
            {
                throw new RuntimeException('collector failed');
            }

            public function push(MessageInterface $message): MessageInterface
            {
                return $message;
            }

            public function status(string|int $id): MessageStatus
            {
                return MessageStatus::DONE;
            }
        };
        $guard = new InstrumentationGuard(
            static function (Throwable $failure) use (&$reported): void {
                $reported = $failure;
            },
        );

        $result = (new QueueProducerDecorator($producer, $collector, $guard))->push($message);

        self::assertSame($message, $result, 'Collector failure must not replace the exact producer result.');
        self::assertInstanceOf(RuntimeException::class, $reported, 'Collector failure must remain observable.');
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

    public function testWorkerDecoratorPreservesExactResultAndThrowableWhenCollectorFails(): void
    {
        $collector = new QueueCollector();
        $collector->startup();
        $message = self::createStub(MessageInterface::class);
        $message->method('getMeta')->willThrowException(new RuntimeException('collector failed'));
        $worker = new class implements WorkerInterface {
            public function process(
                MessageInterface $message,
                string $queueName,
                QueueProducerInterface|null $retryProducer = null,
            ): MessageInterface {
                return $message;
            }
        };
        $decorator = new QueueWorkerDecorator($worker, $collector);

        self::assertSame(
            $message,
            $decorator->process($message, 'jobs'),
            'Collector failure must not replace the exact worker result.',
        );

        $primary = new RuntimeException('worker failed');
        $failingWorker = new class ($primary) implements WorkerInterface {
            public function __construct(private readonly RuntimeException $failure) {}

            public function process(
                MessageInterface $message,
                string $queueName,
                QueueProducerInterface|null $retryProducer = null,
            ): MessageInterface {
                throw $this->failure;
            }
        };

        try {
            (new QueueWorkerDecorator($failingWorker, $collector))->process($message, 'jobs');
            self::fail('Worker failure must propagate.');
        } catch (RuntimeException $failure) {
            self::assertSame($primary, $failure, 'Collector failure must not replace the exact worker throwable.');
        }
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
