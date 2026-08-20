<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\MailCollector;
use Yiisoft\Mailer\Message;

use function file_get_contents;
use function is_dir;
use function is_file;
use function rmdir;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function unlink;

/**
 * Unit tests for {@see MailCollector} covering metadata normalization, persistence, cleanup, and lifecycle reset.
 */
#[Group('collector')]
#[Group('mail')]
final class MailCollectorTest extends TestCase
{
    private string $path = '';

    public function testCollectPersistsMessageAndNormalizesMetadata(): void
    {
        $collector = new MailCollector($this->path, 0o777, 0o666);
        $collector->startup();
        $collector->collectMessage(
            new Message(
                charset: 'UTF-8',
                from: ['sender@example.test' => 'Sender'],
                to: ['recipient@example.test'],
                replyTo: 'reply@example.test',
                subject: 'Phase 5 mail',
                date: new DateTimeImmutable('@1700000000'),
                textBody: 'Mail body',
                headers: ['X-Debug' => ['one', 'two']],
            ),
            true,
        );

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must produce a snapshot.');
        $message = $snapshot->entries()[0] ?? self::fail('Expected one captured mail message.');
        self::assertSame('Sender <sender@example.test>', $message->from, 'Named sender must remain readable.');
        self::assertSame(['recipient@example.test'], $message->to, 'Recipient list must round-trip.');
        self::assertSame('reply@example.test', $message->replyTo[0] ?? null, 'Reply-to must round-trip.');
        self::assertSame("X-Debug: one\r\nX-Debug: two", $message->headers, 'Header values must be preserved.');
        self::assertSame(1_700_000_000, $message->time, 'Explicit message date must round-trip.');
        self::assertTrue($message->isSuccessful, 'Successful outcome must be retained.');
        self::assertSame([$message->file], $collector->messageFiles(), 'Summary files must match the panel record.');

        $file = $this->path . '/' . $message->file;
        self::assertTrue(is_file($file), 'Captured message must be persisted as an `.eml` file.');
        self::assertStringContainsString('Mail body', (string) file_get_contents($file), 'Persisted file must contain the message body.');

        $collector->removeFiles(['../outside.eml', $message->file]);
        self::assertFalse(is_file($file), 'Safe captured file must be removed during snapshot garbage collection.');
    }

    public function testLifecycleIgnoresMessagesWhileInactiveAndClearsState(): void
    {
        $collector = new MailCollector($this->path, 0o777, 0o666);
        $collector->collectMessage(new Message(textBody: 'ignored'), false);

        self::assertNull($collector->capture(), 'Inactive collector must ignore integrations.');

        $collector->startup();
        $collector->shutdown();

        self::assertNull($collector->capture(), 'Shutdown must deactivate and clear the collector.');
    }

    public function testReconcileFilesRemovesOnlyAgedUnreferencedMail(): void
    {
        mkdir($this->path, recursive: true);
        $referenced = $this->path . '/referenced.eml';
        $orphan = $this->path . '/orphan.eml';
        $fresh = $this->path . '/fresh.eml';

        file_put_contents($referenced, 'referenced');
        file_put_contents($orphan, 'orphan');
        file_put_contents($fresh, 'fresh');
        touch($referenced, time() - 90_000);
        touch($orphan, time() - 90_000);

        (new MailCollector($this->path))->reconcileFiles(['referenced.eml', '../unsafe.eml']);

        self::assertFileExists($referenced, 'Manifest-referenced mail must survive reconciliation.');
        self::assertFileDoesNotExist($orphan, 'Aged unreferenced mail must be removed for eventual cleanup retry.');
        self::assertFileExists($fresh, 'Fresh mail must remain available to a concurrent snapshot commit.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii3-debug-mail-collector-' . uniqid('', true);
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
