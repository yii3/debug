<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use Yii3\Debug\Log\DebugLogTarget;
use Yiisoft\Log\Logger;

use function is_array;
use function is_int;

/**
 * Captures every Yii3 log message emitted during the request for the Logs panel.
 *
 * Reads the in-memory debug log target and converts each PSR-3 message into the shared logger tuple shape, mapping
 * the PSR-3 severity names onto the shared wire levels.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\LogCollector($target, $logger))->capture();
 * ```
 */
final class LogCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @param DebugLogTarget $target In-memory target accumulating the request log.
     * @param LoggerInterface|null $logger Application logger flushed before capture, or `null` when unavailable.
     */
    public function __construct(
        private readonly DebugLogTarget $target,
        private readonly LoggerInterface|null $logger = null,
    ) {}

    /**
     * Snapshots the accumulated log messages in the shared Logs payload shape.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return LogSnapshot|null Captured log payload; `null` when the collector never started.
     */
    public function capture(): LogSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        if ($this->logger instanceof Logger) {
            $this->logger->flush();
        }

        $tuples = [];

        foreach ($this->target->messages() as $message) {
            $memory = $message->context('memory');
            $trace = $message->trace();
            $tuples[] = [
                $message->message(),
                self::wireLevel($message->level()),
                $message->category(),
                (float) $message->time()->format('U.u'),
                is_array($trace) ? $trace : [],
                is_int($memory) ? $memory : 0,
            ];
        }

        return LogSnapshot::capture($tuples);
    }

    /**
     * Returns the stable ID pairing this collector with the Logs panel.
     *
     * Usage example:
     *
     * ```php
     * $id = $collector->id();
     * ```
     *
     * @return string Stable collector ID.
     */
    public function id(): string
    {
        return 'log';
    }

    /**
     * Deactivates the collector and clears the accumulated target, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        if (!$this->active) {
            return;
        }

        $this->active = false;

        $this->target->reset();
    }

    /**
     * Activates the collector and starts from an empty accumulator.
     */
    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;

        $this->target->reset();
    }

    /**
     * Maps a PSR-3 severity name onto the shared wire level.
     *
     * @param string $level PSR-3 severity name.
     *
     * @return int Shared wire level.
     */
    private static function wireLevel(string $level): int
    {
        return match ($level) {
            PsrLogLevel::EMERGENCY, PsrLogLevel::ALERT, PsrLogLevel::CRITICAL, PsrLogLevel::ERROR => LogLevel::ERROR,
            PsrLogLevel::WARNING => LogLevel::WARNING,
            PsrLogLevel::NOTICE, PsrLogLevel::INFO => LogLevel::INFO,
            default => LogLevel::TRACE,
        };
    }
}
