<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Yii3\Debug\Collector\MailCollector;
use Yii3\Debug\Mail\MailFileStore;
use Yii3\Debug\Tests\Support\{Captured, PackageConfiguration, TemporaryDirectory};
use Yii3\Debug\Tests\Support\Stubs\RecordingLoggerStub;
use Yiisoft\Mailer\Event\AfterSend;
use Yiisoft\Mailer\Message;

use function array_keys;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function time;
use function touch;

/**
 * Unit tests for {@see MailCollector} lifecycle, message capture with and without the Symfony adapter, and `.eml`
 * storage.
 */
final class MailCollectorTest extends TestCase
{
    private string $root = '';

    public function testCaptureKeepsTheYii2PayloadKeys(): void
    {
        $collector = $this->collector();

        $collector->startup();

        $collector->collect(new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body')));

        $payload = $collector->capture() ?? [];

        $entries = $payload['entries'] ?? null;

        self::assertSame(
            ['entries'],
            array_keys($payload),
            "Payload must hold only 'entries'.",
        );
        self::assertIsArray(
            $entries,
            'Entries must be a list.',
        );
        self::assertIsArray(
            $entries[0] ?? null,
            'Entry must be an object.',
        );
        self::assertSame(
            [
                'from',
                'to',
                'cc',
                'bcc',
                'replyTo',
                'subject',
                'body',
                'headers',
                'charset',
                'file',
                'isSuccessful',
                'time',
            ],
            array_keys($entries[0]),
            'Entry keys must match the Yii2 capture byte for byte.',
        );
    }

    public function testCaptureReturnsNullBeforeStartup(): void
    {
        self::assertNull(
            Captured::mail($this->collector()),
            'Inactive collector must not produce a payload.',
        );
    }

    public function testCollectBeforeStartupIsIgnored(): void
    {
        $collector = $this->collector();

        $collector->collect(new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com')));

        $collector->startup();

        self::assertSame(
            [],
            Captured::mail($collector)?->entries(),
            'Early message must not be captured.',
        );
        self::assertSame(
            [],
            $collector->mailFiles(),
            'Early message must not be stored.',
        );
    }

    public function testCollectReadsMessageAccessorsWithoutSymfonyAdapter(): void
    {
        $logger = new RecordingLoggerStub();
        $collector = new MailCollector(new MailFileStore("{$this->root}/mail"), logger: $logger);
        $message = new Message(
            charset: 'utf-8',
            from: 'a@example.com',
            to: 'b@example.com',
            subject: 'Plain',
            date: new DateTimeImmutable('@1700000000'),
            textBody: 'Body',
            headers: ['X-Custom' => ['one', 'two']],
        );

        $collector->startup();

        $collector->collect(new AfterSend($message));

        $entry = Captured::mail($collector)?->entries()[0] ?? null;

        self::assertNotNull(
            $entry,
            'Message must be captured.',
        );
        self::assertSame(
            'Body',
            $entry->getBody(),
            'Body must come from the text body.',
        );
        self::assertSame(
            'utf-8',
            $entry->getCharset(),
            'Charset must come from the message.',
        );
        self::assertSame(
            "X-Custom: one\r\nX-Custom: two\r\n",
            $entry->getHeaders(),
            'Each header value is one line.',
        );
        self::assertSame(
            1_700_000_000,
            $entry->getTime(),
            'Time must come from the message date.',
        );
        self::assertSame(
            (string) $message,
            file_get_contents("{$this->root}/mail/{$entry->getFile()}"),
            'File must hold the message string representation.',
        );
        self::assertSame(
            [],
            $logger->records,
            'Missing adapter is not a failure.',
        );
        self::assertSame(
            [],
            $entry->getCc(),
            'Missing address must yield no recipient.',
        );
    }

    public function testCollectReadsSymfonyEmailLikeTheYii2Collector(): void
    {
        $collector = $this->collector();

        $collector->startup();

        $collector->collect(
            new AfterSend(
                new Message(
                    from: ['noreply@example.com' => 'Mailer'],
                    to: ['admin@example.com', 'ops@example.com'],
                    replyTo: 'visitor@example.com',
                    cc: 'cc@example.com',
                    bcc: ['hidden@example.com' => 'Hidden'],
                    subject: 'Contact',
                    textBody: 'Hello.',
                ),
            ),
        );

        $entry = Captured::mail($collector)?->entries()[0] ?? null;

        self::assertNotNull(
            $entry,
            'Message must be captured.',
        );
        self::assertSame(
            'noreply@example.com',
            $entry->getFrom(),
            'Display name must be dropped.',
        );
        self::assertSame(
            ['admin@example.com', 'ops@example.com'],
            $entry->getTo(),
            'List entries are addresses.',
        );
        self::assertSame(
            ['cc@example.com'],
            $entry->getCc(),
            'String address must be kept.',
        );
        self::assertSame(
            ['hidden@example.com'],
            $entry->getBcc(),
            'Blind copy must be captured.',
        );
        self::assertSame(
            ['visitor@example.com'],
            $entry->getReplyTo(),
            'Reply-to must be captured.',
        );
        self::assertSame(
            'Contact',
            $entry->getSubject(),
            'Subject must be captured.',
        );
        self::assertSame(
            'Hello.',
            $entry->getBody(),
            'Plain text part must be the body.',
        );
        self::assertSame(
            'text/plain charset: utf-8',
            $entry->getCharset(),
            'Charset must be the part debug string.',
        );
        self::assertSame(
            "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n",
            $entry->getHeaders(),
            'Headers must be the prepared part headers.',
        );
        self::assertTrue(
            $entry->isSuccessful(),
            'Sent message must be reported as sent.',
        );
        self::assertNull(
            $entry->getTime(),
            'Undated email must carry no time.',
        );
        self::assertSame(
            [$entry->getFile()],
            $collector->mailFiles(),
            'Stored file must be listed.',
        );
        self::assertStringContainsString(
            "Subject: Contact\r\n",
            (string) file_get_contents("{$this->root}/mail/{$entry->getFile()}"),
            'File must hold the RFC 5322 source.',
        );
    }

    public function testCollectWithFailingConverterLogsAndReadsMessageAccessors(): void
    {
        $logger = new RecordingLoggerStub();
        $collector = new MailCollector(
            new MailFileStore("{$this->root}/mail"),
            static fn(): never => throw new RuntimeException('Converter changed.'),
            $logger,
        );
        $message = new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body');

        $collector->startup();

        $collector->collect(new AfterSend($message));

        $entry = Captured::mail($collector)?->entries()[0] ?? null;

        self::assertNotNull(
            $entry,
            'Message must still be captured.',
        );
        self::assertSame(
            'Body',
            $entry->getBody(),
            'Body must come from the message accessors.',
        );
        self::assertSame(
            (string) $message,
            file_get_contents("{$this->root}/mail/{$entry->getFile()}"),
            'File must hold the message string representation.',
        );
        self::assertSame(
            [
                [
                    LogLevel::WARNING,
                    'Unable to convert captured mail to a Symfony email: Converter changed.',
                    ['category' => MailCollector::class],
                ],
            ],
            $logger->records,
            'Failure must be logged once as a warning.',
        );

        $silent = new MailCollector(
            new MailFileStore("{$this->root}/mail"),
            static fn(): never => throw new RuntimeException('Converter changed.'),
        );

        $silent->startup();

        $silent->collect(new AfterSend($message));

        self::assertCount(
            1,
            Captured::mail($silent)?->entries() ?? [],
            'Failure without a logger must still capture.',
        );
    }

    public function testCollectWithSymfonyAdapterLeavesBodyEmptyForNonPlainParts(): void
    {
        $collector = $this->collector();

        $collector->startup();

        $collector->collect(
            new AfterSend(
                new Message(
                    charset: 'iso-8859-1',
                    from: 'a@example.com',
                    to: 'b@example.com',
                    date: new DateTimeImmutable('@1700000000'),
                    htmlBody: '<b>Hi</b>',
                ),
            ),
        );

        $entry = Captured::mail($collector)?->entries()[0] ?? null;

        self::assertNotNull(
            $entry,
            'Message must be captured.',
        );
        self::assertSame(
            '',
            $entry->getBody(),
            'HTML part must not be read as the body.',
        );
        self::assertSame(
            'iso-8859-1',
            $entry->getCharset(),
            'Charset must fall back to the message.',
        );
        self::assertStringStartsWith(
            "Content-Type: text/html; charset=iso-8859-1\r\n",
            $entry->getHeaders(),
            'Headers must describe the HTML part.',
        );
        self::assertSame(
            1_700_000_000,
            $entry->getTime(),
            'Time must come from the email date.',
        );
    }

    public function testCollectWithSymfonyAdapterStoresMessageStringWhenEmailCannotBeSerialized(): void
    {
        $collector = $this->collector();

        $message = new Message(to: 'b@example.com', subject: 'No sender', textBody: 'Body');

        $collector->startup();

        $collector->collect(new AfterSend($message));

        $entry = Captured::mail($collector)?->entries()[0] ?? null;

        self::assertNotNull(
            $entry,
            'Message must still be captured.',
        );
        self::assertSame(
            'Body',
            $entry->getBody(),
            'Body must still come from the Symfony part.',
        );
        self::assertSame(
            (string) $message,
            file_get_contents("{$this->root}/mail/{$entry->getFile()}"),
            'File must fall back to the message string representation.',
        );
    }

    public function testFailedStorageKeepsMessageWithoutFile(): void
    {
        file_put_contents("{$this->root}/blocker", 'not a directory');

        $collector = new MailCollector(
            new MailFileStore("{$this->root}/blocker/mail"),
            PackageConfiguration::emailFactory(),
        );

        $collector->startup();

        $collector->collect(new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body')));

        self::assertSame(
            '',
            Captured::mail($collector)?->entries()[0]?->getFile(),
            'Entry must carry no file.',
        );
        self::assertSame(
            [],
            $collector->mailFiles(),
            'Unstored message must not be listed.',
        );
    }

    public function testIdIsMail(): void
    {
        self::assertSame(
            'mail',
            $this->collector()->id(),
            'ID must pair the collector with the Mail panel.',
        );
    }

    public function testRemoveAndReconcileDelegateToTheFileStore(): void
    {
        $mail = "{$this->root}/mail";

        mkdir($mail);
        file_put_contents("{$mail}/evicted.eml", 'evicted');
        file_put_contents("{$mail}/orphan.eml", 'orphan');
        touch("{$mail}/orphan.eml", time() - 90_000);

        $collector = $this->collector();

        $collector->removeFiles(['evicted.eml']);
        $collector->reconcileFiles([]);

        self::assertFileDoesNotExist(
            "{$mail}/evicted.eml",
            'Evicted file must be removed.',
        );
        self::assertFileDoesNotExist(
            "{$mail}/orphan.eml",
            'Aged orphan must be reconciled.',
        );
    }

    public function testShutdownClearsMessagesAndStartupAfterShutdownStartsClean(): void
    {
        $collector = $this->collector();

        $collector->startup();

        $collector->collect(new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body')));

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must not produce a payload.',
        );
        self::assertSame(
            [],
            $collector->mailFiles(),
            'Stopped collector must list no file.',
        );

        $collector->collect(new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body')));

        $collector->startup();

        self::assertSame(
            [],
            Captured::mail($collector)?->entries(),
            'Restarted collector must start empty.',
        );
        self::assertSame(
            [],
            $collector->mailFiles(),
            'Restarted collector must list no file.',
        );
    }

    public function testStartupTwiceKeepsCollectedMessages(): void
    {
        $collector = $this->collector();

        $collector->startup();

        $collector->collect(new AfterSend(new Message(from: 'a@example.com', to: 'b@example.com', textBody: 'Body')));

        $collector->startup();

        self::assertCount(
            1,
            Captured::mail($collector)?->entries() ?? [],
            'Second startup must not clear messages.',
        );
        self::assertCount(
            1,
            $collector->mailFiles(),
            'Second startup must not clear stored files.',
        );
    }

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-mail-collector-');
    }

    protected function tearDown(): void
    {
        TemporaryDirectory::remove($this->root);
    }

    private function collector(): MailCollector
    {
        return new MailCollector(new MailFileStore("{$this->root}/mail"), PackageConfiguration::emailFactory());
    }
}
