<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Storage\Json;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use Yii3\Debug\Log\DebugLogTarget;
use Yiisoft\Log\{Logger, Message};

use function in_array;
use function is_array;
use function is_int;
use function is_string;

/**
 * Captures Yiisoft log messages in the canonical Log panel shape.
 */
final class LogCollector implements CollectorInterface
{
    /**
     * Whether the collector is capturing for the current request.
     */
    private bool $started = false;

    /**
     * @param DebugLogTarget $target Log target accumulating the messages of the request.
     * @param LoggerInterface|null $logger Application logger flushed before reading, or `null` to skip flushing.
     */
    public function __construct(
        private readonly DebugLogTarget $target,
        private readonly LoggerInterface|null $logger = null,
    ) {}

    /**
     * Encodes the captured messages into the Logs panel payload.
     *
     * @return array<string, mixed>|null Encoded Logs panel payload; `null` when the collector never started.
     */
    public function capture(): array|null
    {
        if ($this->started === false) {
            return null;
        }

        $this->flushLogger();

        $messages = [];

        foreach ($this->target->messages() as $message) {
            $memory = $message->context('memory');

            $messages[] = [
                Json::safeString($message->message()),
                self::wireLevel($message->level()),
                Json::safeString($message->category()),
                (float) $message->time()->format('U.u'),
                self::trace($message),
                is_int($memory) ? $memory : 0,
            ];
        }

        return LogSnapshot::capture($messages)->jsonSerialize();
    }

    /**
     * Returns the stable identifier of this collector.
     *
     * @return string Stable ID pairing this collector with its panel.
     */
    public function id(): string
    {
        return 'log';
    }

    /**
     * Stops capturing and clears the messages accumulated for the request.
     */
    public function shutdown(): void
    {
        $this->started = false;

        $this->flushAndReset();
    }

    /**
     * Starts capturing, discarding anything the target held from a previous request.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->flushAndReset();

        $this->started = true;
    }

    /**
     * Delivers pending messages when the request target is attached, then resets its request-scoped state.
     */
    private function flushAndReset(): void
    {
        $this->flushLogger();

        $this->target->reset();
    }

    /**
     * Flushes the application logger, so messages still buffered reach the target before it is read.
     */
    private function flushLogger(): void
    {
        if (
            !$this->logger instanceof Logger
            || !in_array($this->target, $this->logger->getTargets(), true)
        ) {
            return;
        }

        $this->logger->flush();
    }

    /**
     * Keeps only the standard scalar backtrace fields and makes every stored string valid UTF-8.
     *
     * @param Message $message Captured log message carrying the raw backtrace.
     *
     * @return list<array<string, int|string>> Sanitized frames in capture order.
     */
    private static function trace(Message $message): array
    {
        $frames = [];

        foreach ($message->trace() ?? [] as $frame) {
            $normalized = self::traceFrame($frame);

            if ($normalized !== null) {
                $frames[] = $normalized;
            }
        }

        return $frames;
    }

    /**
     * Narrows one untrusted runtime trace value despite the stronger upstream PHPDoc declaration.
     *
     * @param mixed $frame Raw backtrace frame of unknown shape.
     *
     * @return array<string, int|string>|null Sanitized frame, or `null` when it carries no usable field.
     */
    private static function traceFrame(mixed $frame): array|null
    {
        if (!is_array($frame)) {
            return null;
        }

        $normalized = [];
        $attributes = ['file', 'line', 'function', 'class', 'type'];

        foreach ($attributes as $attribute) {
            $value = $frame[$attribute] ?? null;

            if ($attribute === 'line') {
                if (is_int($value)) {
                    $normalized[$attribute] = $value;
                }

                continue;
            }

            if (is_string($value)) {
                $normalized[$attribute] = Json::safeString($value);
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * Maps a PSR-3 level name to the numeric level used by the shared snapshot.
     *
     * @param string $level PSR-3 level name.
     *
     * @return int Numeric level used by the shared log snapshot.
     */
    private static function wireLevel(string $level): int
    {
        return match ($level) {
            PsrLogLevel::EMERGENCY,
            PsrLogLevel::ALERT,
            PsrLogLevel::CRITICAL,
            PsrLogLevel::ERROR => LogLevel::ERROR,
            PsrLogLevel::WARNING => LogLevel::WARNING,
            PsrLogLevel::NOTICE,
            PsrLogLevel::INFO => LogLevel::INFO,
            default => LogLevel::TRACE,
        };
    }
}
