<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Integration;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use PHPForge\Debug\Panel\Mail\MailMessage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Yii3\Debug\Collector\MailCollector;
use Yii3\Debug\Integration\MailerInterfaceProxy;
use Yiisoft\Mailer\{MailerInterface, Message, MessageInterface, SendResults, StubMailer};

use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Integration tests for {@see MailerInterfaceProxy} reporting definitive single and batch send outcomes.
 */
#[Group('mail')]
final class MailerInterfaceProxyTest extends TestCase
{
    private string $path = '';

    public function testSendAndSendMultipleCaptureSuccessfulMessages(): void
    {
        $collector = new MailCollector($this->path, 0o777, 0o666);
        $collector->startup();
        $proxy = new MailerInterfaceProxy(new StubMailer(), $collector);

        $proxy->send(new Message(subject: 'single', textBody: 'one'));
        $proxy->sendMultiple([
            new Message(subject: 'batch one', textBody: 'two'),
            new Message(subject: 'batch two', textBody: 'three'),
        ]);

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must capture proxy sends.');
        self::assertCount(3, $snapshot->entries(), 'Single and batch sends must be recorded exactly once.');
        self::assertSame(0, MailMessage::failedCount($snapshot->entries()), 'Stub mailer results must be successful.');
    }

    public function testSendCapturesFailureAndRethrowsMailerException(): void
    {
        $collector = new MailCollector($this->path, 0o777, 0o666);
        $collector->startup();
        $proxy = new MailerInterfaceProxy(
            new class implements MailerInterface {
                public function send(MessageInterface $message): void
                {
                    throw new RuntimeException('transport failed');
                }

                public function sendMultiple(array $messages): SendResults
                {
                    throw new RuntimeException('batch failed');
                }
            },
            $collector,
        );

        try {
            $proxy->send(new Message(subject: 'failure', textBody: 'body'));
            self::fail('Mailer failure must be rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('transport failed', $exception->getMessage(), 'Original transport exception must survive.');
        }

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Failed sends must still be inspectable.');
        $message = $snapshot->entries()[0] ?? self::fail('Expected the failed captured message.');
        self::assertFalse($message->isSuccessful, 'Failed outcome must be captured before rethrowing.');
    }

    public function testSendPreservesMailerBehaviorWhenDebugCollectionFails(): void
    {
        $collector = new MailCollector($this->path, 0o777, 0o666);
        $collector->startup();
        $message = self::createStub(MessageInterface::class);
        $message->method('getBcc')->willThrowException(new RuntimeException('collector failed'));
        $reported = null;
        $guard = new InstrumentationGuard(
            static function (Throwable $failure) use (&$reported): void {
                $reported = $failure;
            },
        );
        $mailer = new class implements MailerInterface {
            public function send(MessageInterface $message): void {}

            public function sendMultiple(array $messages): SendResults
            {
                return new SendResults([], []);
            }
        };
        $proxy = new MailerInterfaceProxy($mailer, $collector, $guard);

        $proxy->send($message);

        self::assertInstanceOf(RuntimeException::class, $reported, 'Collector failure must remain observable.');
        self::assertSame('collector failed', $reported->getMessage(), 'Diagnostic observer must receive collector failure.');

        $primary = new RuntimeException('transport failed');
        $failingMailer = new class ($primary) implements MailerInterface {
            public function __construct(private readonly RuntimeException $failure) {}

            public function send(MessageInterface $message): void
            {
                throw $this->failure;
            }

            public function sendMultiple(array $messages): SendResults
            {
                throw $this->failure;
            }
        };

        try {
            (new MailerInterfaceProxy($failingMailer, $collector, $guard))->send($message);
            self::fail('Transport failure must propagate.');
        } catch (RuntimeException $failure) {
            self::assertSame($primary, $failure, 'Collector failure must not replace the exact transport throwable.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-mail-proxy-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->path . '/*');

        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        parent::tearDown();
    }
}
