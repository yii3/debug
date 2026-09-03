<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
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
    private bool $started = false;

    public function __construct(
        private readonly DebugLogTarget $target,
        private readonly LoggerInterface|null $logger = null,
    ) {}

    public function capture(): LogSnapshot|null
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

        return LogSnapshot::capture($messages);
    }

    public function id(): string
    {
        return 'log';
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->flushAndReset();
    }

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
     * @return list<array<string, int|string>>
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
     * @return array<string, int|string>|null
     */
    private static function traceFrame(mixed $frame): array|null
    {
        if (!is_array($frame)) {
            return null;
        }

        $normalized = [];

        foreach (['file', 'line', 'function', 'class', 'type'] as $attribute) {
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
