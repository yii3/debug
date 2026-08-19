<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Collector\QueueCollector;
use Yiisoft\Queue\Message\{GenericMessage, MessageInterface};
use Yiisoft\Queue\{MessageStatus, QueueProducerInterface};

/**
 * Unit tests for {@see QueueCollector} covering push, execution, metadata, redaction, and lifecycle behavior.
 */
#[Group('collector')]
#[Group('queue')]
final class QueueCollectorTest extends TestCase
{
    public function testLifecycleIgnoresRecordsWhileInactiveAndClearsState(): void
    {
        $collector = new QueueCollector();
        $collector->recordPush($this->producer(), GenericMessage::fromPayload('ignored', null));

        self::assertNull($collector->capture(), 'Inactive collector must ignore integrations.');

        $collector->startup();
        $collector->shutdown();

        self::assertNull($collector->capture(), 'Shutdown must deactivate and clear the collector.');
    }

    public function testRecordExecutionCapturesSuccessAndFailure(): void
    {
        $collector = new QueueCollector();
        $collector->startup();
        $message = GenericMessage::fromPayload('report', ['id' => 42])->withMeta(['attempt' => 2]);

        $collector->recordExecution('reports', $message, 0.25);
        $collector->recordExecution('reports', $message, 0.5, new RuntimeException('worker failed'));

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must produce a snapshot.');
        $records = $snapshot->entries();
        $success = $records[0] ?? self::fail('Expected one successful execution record.');
        $failure = $records[1] ?? self::fail('Expected one failed execution record.');
        self::assertSame(JobRecord::TYPE_EXEC, $success->eventType, 'Successful worker result must record exec.');
        self::assertSame(0.25, $success->duration, 'Successful duration must round-trip.');
        self::assertSame(2, $success->attempt, 'Attempt metadata must round-trip.');
        self::assertSame(JobRecord::TYPE_ERROR, $failure->eventType, 'Thrown worker result must record error.');
        self::assertSame('worker failed', $failure->error, 'Worker exception message must round-trip.');
    }
    public function testRecordPushCapturesMetadataAndRedactsNestedPayload(): void
    {
        $collector = new QueueCollector(['password', 'accessToken']);
        $collector->startup();
        $message = GenericMessage::fromPayload(
            'App\\Message\\SendMail',
            ['email' => 'ada@example.test', 'password' => 'secret', 'nested' => ['accessToken' => 'token']],
        )->withMeta(['yii-id' => 'job-7', 'yii-delay' => 5.0, 'ttr' => 60, 'priority' => 10]);

        $collector->recordPush($this->producer(), $message);

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must produce a snapshot.');
        $record = $snapshot->entries()[0] ?? self::fail('Expected one push record.');
        self::assertSame(JobRecord::TYPE_PUSH, $record->eventType, 'Producer integration must record a push event.');
        self::assertSame('emails', $record->componentId, 'Logical queue name must identify the component.');
        self::assertSame('job-7', $record->jobId, 'Queue-assigned ID must round-trip.');
        self::assertSame(5, $record->delay, 'Queue delay metadata must round-trip in seconds.');
        self::assertSame(60, $record->ttr, 'TTR metadata must round-trip.');
        self::assertSame(10, $record->priority, 'Priority metadata must round-trip.');
        self::assertSame(
            [
                'payload' => [
                    'email' => 'ada@example.test',
                    'password' => '[redacted]',
                    'nested' => ['accessToken' => '[redacted]'],
                ],
            ],
            $record->payloadFields,
            'Configured sensitive payload keys must be redacted recursively.',
        );
    }

    private function producer(): QueueProducerInterface
    {
        return new class implements QueueProducerInterface {
            public function getQueueName(): string
            {
                return 'emails';
            }

            public function push(MessageInterface $message): MessageInterface
            {
                return $message;
            }

            public function status(string|int $id): MessageStatus
            {
                return MessageStatus::WAITING;
            }
        };
    }
}
