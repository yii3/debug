<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Closure;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\TextPart;
use Throwable;
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Mail\MailFileStore;
use Yiisoft\Mailer\Event\AfterSend;
use Yiisoft\Mailer\MessageInterface;

use function implode;
use function is_array;
use function is_int;

/**
 * Captures every message `yiisoft/mailer` sends during the request for the Mail panel.
 *
 * Each message is recorded in the capture shape the Yii2 collector produces, so a capture written by either host
 * renders through the same {@see \PHPForge\Debug\Panel\Mail\MailPanel}, and is stored as an `.eml` file through
 * {@see MailFileStore}. `yiisoft/mailer` dispatches {@see AfterSend} only once the transport accepted the message, so
 * every captured message is reported as sent.
 */
final class MailCollector implements CollectorInterface
{
    /**
     * Captured messages in send order, in the shape {@see MailSnapshot::capture()} reads.
     *
     * @var list<array<string, mixed>>
     */
    private array $messages = [];
    /**
     * Indicates whether collection is active for the current request lifecycle.
     */
    private bool $started = false;
    /**
     * Names of the `.eml` files stored for the captured messages, in send order.
     *
     * @var list<string>
     */
    private array $storedFiles = [];

    /**
     * @param MailFileStore $files Store the `.eml` files are written to and removed from.
     * @param (Closure(MessageInterface): Email)|null $emailFactory Converter building the Symfony email the
     * `yiisoft/mailer-symfony` adapter sends, or `null` when the adapter is not installed and the message accessors are
     * read instead.
     * @param LoggerInterface|null $logger Logger receiving a warning when the conversion fails, or `null` to fall back
     * to the message accessors without reporting it.
     */
    public function __construct(
        private readonly MailFileStore $files,
        private readonly Closure|null $emailFactory = null,
        private readonly LoggerInterface|null $logger = null,
    ) {}

    /**
     * Encodes the captured messages into the Mail panel payload.
     *
     * @return array<string, mixed>|null Encoded Mail panel payload; `null` when the collector never started.
     */
    public function capture(): array|null
    {
        if (!$this->started) {
            return null;
        }

        return MailSnapshot::capture($this->messages)->jsonSerialize();
    }

    /**
     * Records a message the mailer sent and stores it as an `.eml` file.
     *
     * With the Symfony email the body, charset, headers, time, and file are read exactly as the Yii2 collector reads
     * them from its Symfony message; without it they come from the message accessors. The file holds the RFC 5322
     * source, or the message string representation when there is no Symfony email or Symfony refuses to serialize one
     * that lacks a sender or recipient.
     *
     * @param AfterSend $event Event the mailer dispatched after the transport accepted the message.
     */
    public function collect(AfterSend $event): void
    {
        if (!$this->started) {
            return;
        }

        $message = $event->message;

        $row = [
            'bcc' => self::addresses($message->getBcc()),
            'cc' => self::addresses($message->getCc()),
            'charset' => $message->getCharset(),
            'from' => self::addresses($message->getFrom()),
            'isSuccessful' => true,
            'reply' => self::addresses($message->getReplyTo()),
            'subject' => $message->getSubject(),
            'to' => self::addresses($message->getTo()),
        ];

        $email = $this->email($message);

        if ($email === null) {
            $row['body'] = $message->getTextBody();
            $row['headers'] = self::headers($message->getHeaders());
            $row['time'] = $message->getDate();

            $file = $this->files->write((string) $message);
        } else {
            $part = $email->getBody();

            $row['body'] = null;

            if ($part instanceof TextPart && $part->getMediaSubtype() === 'plain') {
                $row['charset'] = $part->asDebugString();
                $row['body'] = $part->getBody();
            }

            $row['headers'] = $part->getPreparedHeaders()->toString();
            $row['time'] = $email->getDate();

            try {
                $file = $this->files->write($email->toString());
            } catch (Throwable) {
                $file = $this->files->write((string) $message);
            }
        }

        $row['file'] = $file;

        $this->messages[] = $row;

        if ($file !== '') {
            $this->storedFiles[] = $file;
        }
    }

    /**
     * Returns the stable identifier of this collector.
     *
     * @return string Stable ID pairing this collector with its panel.
     */
    public function id(): string
    {
        return 'mail';
    }

    /**
     * Returns the names of the `.eml` files stored for the captured messages.
     *
     * The request summary records them, so evicting the capture from history also removes its files.
     *
     * @return list<string> File names in send order; messages whose file could not be written are skipped.
     */
    public function mailFiles(): array
    {
        return $this->storedFiles;
    }

    /**
     * Deletes aged `.eml` files no retained capture refers to.
     *
     * @param iterable<string> $referencedFiles File names referenced by the committed manifest.
     */
    public function reconcileFiles(iterable $referencedFiles): void
    {
        $this->files->reconcile($referencedFiles);
    }

    /**
     * Deletes the `.eml` files of a capture that was evicted from history or never committed.
     *
     * @param iterable<string> $files File names recorded by the capture.
     */
    public function removeFiles(iterable $files): void
    {
        $this->files->remove($files);
    }

    /**
     * Stops capturing and clears the messages accumulated for the request.
     */
    public function shutdown(): void
    {
        $this->started = false;
        $this->messages = [];
        $this->storedFiles = [];
    }

    /**
     * Starts capturing, discarding anything left from a previous request.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->messages = [];
        $this->storedFiles = [];
        $this->started = true;
    }

    /**
     * Flattens a mailer address value into the comma-separated address list the Yii2 collector records.
     *
     * Display names are dropped: an `address => name` pair contributes its address and a list entry contributes
     * itself.
     *
     * @param array<array-key, string>|string|null $addresses Address value as the message holds it.
     *
     * @return string Comma-separated addresses, or `''` when the message holds none.
     */
    private static function addresses(array|string|null $addresses): string
    {
        if (!is_array($addresses)) {
            return $addresses ?? '';
        }

        $list = [];

        foreach ($addresses as $address => $name) {
            $list[] = is_int($address) ? $name : $address;
        }

        return implode(', ', $list);
    }

    /**
     * Converts the message to the email the Symfony adapter sends.
     *
     * A failing conversion is logged and the message accessors are read instead, so the listener never interrupts the
     * application after the mail was sent.
     *
     * @param MessageInterface $message Message the mailer sent.
     *
     * @return Email|null Symfony email, or `null` when no converter is configured or the conversion failed.
     */
    private function email(MessageInterface $message): Email|null
    {
        if ($this->emailFactory === null) {
            return null;
        }

        try {
            return ($this->emailFactory)($message);
        } catch (Throwable $failure) {
            $this->logger?->warning(
                Message::CAPTURED_MAIL_EMAIL_CONVERSION_FAILED->getMessage($failure->getMessage()),
                ['category' => self::class],
            );

            return null;
        }
    }

    /**
     * Formats the custom headers of a message as header lines.
     *
     * @param array<string, list<string>>|null $headers Headers keyed by name, as the message holds them.
     *
     * @return string Header lines, each terminated by CRLF, or `''` when the message holds none.
     */
    private static function headers(array|null $headers): string
    {
        $lines = '';

        foreach ($headers ?? [] as $name => $values) {
            foreach ($values as $value) {
                $lines .= "{$name}: {$value}\r\n";
            }
        }

        return $lines;
    }
}
