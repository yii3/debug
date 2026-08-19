<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use RuntimeException;
use Throwable;
use Yiisoft\Mailer\MessageInterface;

use function basename;
use function bin2hex;
use function chmod;
use function date;
use function file_put_contents;
use function implode;
use function is_array;
use function is_dir;
use function is_int;
use function is_string;
use function mkdir;
use function random_bytes;
use function sprintf;
use function str_contains;
use function time;
use function trim;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;

/**
 * Captures messages sent through the Yii3 mailer debug decorator and persists safe `.eml` files.
 */
final class MailCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @var list<string> Persistence failures surfaced during the isolated snapshot capture stage.
     */
    private array $failures = [];

    /**
     * @var list<array<string, mixed>> Captured messages in send order.
     */
    private array $messages = [];

    public function __construct(
        private readonly string $mailPath,
        private readonly int $dirMode = 0o775,
        private readonly int $fileMode = 0o664,
    ) {}

    public function capture(): MailSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        if ($this->failures !== []) {
            throw new RuntimeException(implode("\n", $this->failures));
        }

        return MailSnapshot::capture($this->messages);
    }

    /**
     * Records one sent or failed message without allowing debugger storage failures to interrupt the mailer.
     */
    public function collectMessage(MessageInterface $message, bool $isSuccessful): void
    {
        if (!$this->active) {
            return;
        }

        $file = '';

        try {
            $file = $this->persist($message);
        } catch (Throwable $throwable) {
            $this->failures[] = $throwable->getMessage();
        }

        $this->messages[] = [
            'bcc' => self::addresses($message->getBcc()),
            'body' => $message->getTextBody() ?? $message->getHtmlBody() ?? '',
            'cc' => self::addresses($message->getCc()),
            'charset' => $message->getCharset() ?? '',
            'file' => $file,
            'from' => self::addresses($message->getFrom()),
            'headers' => self::headers($message->getHeaders()),
            'isSuccessful' => $isSuccessful,
            'reply' => self::addresses($message->getReplyTo()),
            'subject' => $message->getSubject() ?? '',
            'time' => $message->getDate()?->getTimestamp() ?? time(),
            'to' => self::addresses($message->getTo()),
        ];
    }

    public function id(): string
    {
        return 'mail';
    }

    /**
     * Returns the configured absolute mail-capture directory.
     */
    public function mailPath(): string
    {
        return $this->mailPath;
    }

    /**
     * @return list<string> Safe captured file names in send order.
     */
    public function messageFiles(): array
    {
        $files = [];

        foreach ($this->messages as $message) {
            $file = $message['file'] ?? '';

            if (is_string($file) && $file !== '') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Removes files associated with snapshots evicted from the debug manifest.
     *
     * @param iterable<string> $files Stored safe file names.
     */
    public function removeFiles(iterable $files): void
    {
        foreach ($files as $file) {
            if (!self::isSafeFile($file)) {
                continue;
            }

            $path = $this->mailPath . DIRECTORY_SEPARATOR . $file;

            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function shutdown(): void
    {
        $this->active = false;
        $this->failures = [];
        $this->messages = [];
    }

    public function startup(): void
    {
        $this->active = true;
        $this->failures = [];
        $this->messages = [];
    }

    /**
     * @param array<array-key, string>|string|null $addresses
     */
    private static function addresses(array|string|null $addresses): string
    {
        if (is_string($addresses)) {
            return $addresses;
        }

        if (!is_array($addresses)) {
            return '';
        }

        $result = [];

        foreach ($addresses as $email => $name) {
            if (is_int($email)) {
                $result[] = $name;

                continue;
            }

            $label = trim($name);
            $result[] = $label === '' ? $email : "{$label} <{$email}>";
        }

        return implode(', ', $result);
    }

    /**
     * @param array<string, list<string>>|null $headers
     */
    private static function headers(array|null $headers): string
    {
        if ($headers === null) {
            return '';
        }

        $lines = [];

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $lines[] = "{$name}: {$value}";
            }
        }

        return implode("\r\n", $lines);
    }

    private static function isSafeFile(string $file): bool
    {
        return $file !== ''
            && basename($file) === $file
            && !str_contains($file, '/')
            && !str_contains($file, '\\');
    }

    private function persist(MessageInterface $message): string
    {
        if (!is_dir($this->mailPath) && !mkdir($this->mailPath, $this->dirMode, true) && !is_dir($this->mailPath)) {
            throw new RuntimeException("Unable to create debug mail directory: {$this->mailPath}");
        }

        $file = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.eml';

        $path = $this->mailPath . DIRECTORY_SEPARATOR . $file;

        if (file_put_contents($path, (string) $message, LOCK_EX) === false) {
            throw new RuntimeException("Unable to persist captured mail file: {$path}");
        }

        if (!chmod($path, $this->fileMode)) {
            unlink($path);

            throw new RuntimeException(
                sprintf('Unable to apply mode %o to captured mail file: %s', $this->fileMode, $path),
            );
        }

        return $file;
    }
}
