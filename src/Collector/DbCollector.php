<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Db\{DbSnapshot, QueryRow};
use Yiisoft\Db\Profiler\{ContextInterface, ProfilerInterface};
use Yiisoft\Profiler\ProfilerInterface as ApplicationProfilerInterface;

use function array_splice;
use function count;
use function debug_backtrace;
use function error_reporting;
use function hash;
use function hash_algos;
use function in_array;
use function json_encode;
use function ltrim;
use function mb_strtoupper;
use function memory_get_usage;
use function microtime;
use function preg_match;
use function str_contains;
use function strrpos;
use function substr;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const E_DEPRECATED;

/**
 * Captures every database query profiled by `yiisoft/db` for the Database panel.
 *
 * Implements the `yiisoft/db` profiler contract, so binding this collector as the connection profiler records each
 * query's SQL, duration, and calling backtrace; the package works without `yiisoft/db` installed — the class only
 * loads when the application wires it.
 *
 * Usage example:
 *
 * ```php
 * $collector = new \Yii3\Debug\Collector\DbCollector();
 * $connection->setProfiler($collector);
 * ```
 */
final class DbCollector implements CollectorInterface, ProfilerInterface
{
    private bool $active = false;

    /**
     * @var list<array{
     *   token: string,
     *   category: string,
     *   start: float,
     *   memory: int,
     *   trace: list<array<string, mixed>>
     * }> Open profile blocks in nesting order.
     */
    private array $open = [];

    /**
     * @var list<array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: list<array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * }> Completed query timings in completion order.
     */
    private array $timings = [];

    private static string|null $traceHashAlgo = null;

    /**
     * @param ApplicationProfilerInterface|null $profiler Optional application profiler receiving the same DB spans.
     */
    public function __construct(private readonly ApplicationProfilerInterface|null $profiler = null) {}

    /**
     * Marks the beginning of a profiled query block.
     *
     * Usage example:
     *
     * ```php
     * $collector->begin($sql, $context);
     * ```
     *
     * @param string $token Profiled token (the raw SQL statement).
     * @param array<array-key, mixed>|ContextInterface $context Profiling context supplied by `yiisoft/db`.
     */
    public function begin(string $token, array|ContextInterface $context = []): void
    {
        if (!$this->active) {
            return;
        }

        $trace = self::backtrace();
        $category = self::profileCategory($context);

        $this->beginApplicationProfile($token, $category, $trace);

        $this->open[] = [
            'token' => $token,
            'category' => $category,
            'start' => microtime(true),
            'memory' => memory_get_usage(),
            'trace' => $trace,
        ];
    }

    /**
     * Snapshots the completed query timings as typed rows.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return DbSnapshot|null Captured query payload; `null` when the collector never started.
     */
    public function capture(): DbSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        $duplicates = [];

        foreach ($this->timings as $timing) {
            $duplicates[$timing['info']] = ($duplicates[$timing['info']] ?? 0) + 1;
        }

        $rows = [];

        foreach ($this->timings as $seq => $timing) {
            $rows[] = QueryRow::fromTiming(
                $timing,
                self::queryType($timing['info']),
                $seq,
                $duplicates[$timing['info']] ?? 1,
                null,
            );
        }

        return new DbSnapshot($rows);
    }

    /**
     * Marks the end of a profiled query block and records its timing.
     *
     * Usage example:
     *
     * ```php
     * $collector->end($sql, $context);
     * ```
     *
     * @param string $token Profiled token (the raw SQL statement).
     * @param array<array-key, mixed>|ContextInterface $context Profiling context supplied by `yiisoft/db`.
     */
    public function end(string $token, array|ContextInterface $context = []): void
    {
        if (!$this->active) {
            return;
        }

        $block = $this->popBlock($token);

        if ($block === null) {
            return;
        }

        $this->profiler?->end($token, ['category' => $block['category']]);

        $now = microtime(true);
        $memory = memory_get_usage();

        $this->timings[] = [
            'info' => $token,
            'category' => $context instanceof ContextInterface ? $context->getType() : 'query',
            'timestamp' => $block['start'],
            'trace' => $block['trace'],
            'level' => 0,
            'duration' => $now - $block['start'],
            'memory' => $memory,
            'memoryDiff' => $memory - $block['memory'],
            'traceHash' => hash(self::traceHashAlgo(), (string) json_encode($block['trace'])),
        ];
    }

    /**
     * Returns the stable ID pairing this collector with the Database panel.
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
        return 'db';
    }

    /**
     * Returns the number of completed queries recorded so far.
     *
     * Usage example:
     *
     * ```php
     * $count = $collector->queryCount();
     * ```
     */
    public function queryCount(): int
    {
        return count($this->timings);
    }

    /**
     * Deactivates the collector and clears the recorded timings, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
        $this->open = [];
        $this->timings = [];
    }

    /**
     * Activates the collector and starts from an empty timing log.
     */
    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;
        $this->open = [];
        $this->timings = [];
    }

    /**
     * Captures the calling backtrace, dropping frames raised inside the debug package and `yiisoft/db` and keeping
     * the ten nearest caller frames.
     *
     * @return list<array<string, mixed>> Backtrace frames nearest caller first.
     */
    private static function backtrace(): array
    {
        $frames = [];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null || str_contains($file, 'yiisoft/db') || str_contains($file, 'yii3/debug/src')) {
                continue;
            }

            $frames[] = $frame;

            if (count($frames) === 10) {
                break;
            }
        }

        return $frames;
    }

    /**
     * Opens the mirrored application profile while isolating the stable profiler release's PHP 8.4 DTO deprecation.
     *
     * @param list<array<string, mixed>> $trace Captured DB caller trace.
     */
    private function beginApplicationProfile(string $token, string $category, array $trace): void
    {
        if ($this->profiler === null) {
            return;
        }

        $errorReporting = error_reporting();

        try {
            error_reporting($errorReporting & ~E_DEPRECATED);

            $this->profiler->begin($token, ['category' => $category, 'trace' => $trace]);
        } finally {
            error_reporting($errorReporting);
        }
    }

    /**
     * Removes and returns the innermost open block matching the token, or `null` when none matches.
     *
     * @param string $token Profiled token.
     *
     * @return array{
     *   token: string,
     *   category: string,
     *   start: float,
     *   memory: int,
     *   trace: list<array<string, mixed>>
     * }|null Matched block.
     */
    private function popBlock(string $token): array|null
    {
        for ($index = count($this->open) - 1; $index >= 0; $index--) {
            $block = $this->open[$index] ?? null;

            if ($block !== null && $block['token'] === $token) {
                array_splice($this->open, $index, 1);

                return $block;
            }
        }

        return null;
    }

    /**
     * Builds the Yii3 profile category matching the profiled database operation.
     *
     * @param array<array-key, mixed>|ContextInterface $context Database profiling context.
     */
    private static function profileCategory(array|ContextInterface $context): string
    {
        if (!$context instanceof ContextInterface) {
            return 'Yiisoft\\Db\\Command::query';
        }

        $type = $context->getType();

        $method = Coerce::string($context->asArray()['method'] ?? null, $type);

        $separator = strrpos($method, '::');

        if ($separator !== false) {
            $method = substr($method, $separator + 2);
        }

        return $type === 'connection'
            ? "Yiisoft\\Db\\Connection::{$method}"
            : "Yiisoft\\Db\\Command::{$method}";
    }

    /**
     * Returns the uppercase SQL command verb extracted from the leading word of the statement.
     *
     * @param string $query Raw SQL statement.
     *
     * @return string Uppercase command verb (`SELECT`, `INSERT`, ...), or `''` when none could be extracted.
     */
    private static function queryType(string $query): string
    {
        preg_match('/^([a-zA-Z]*)/', ltrim($query), $matches);

        return isset($matches[0]) ? mb_strtoupper($matches[0], 'UTF-8') : '';
    }

    /**
     * Returns the hash algorithm used to fingerprint backtraces, preferring `xxh3` with a `crc32` fallback.
     */
    private static function traceHashAlgo(): string
    {
        if (self::$traceHashAlgo === null) {
            self::$traceHashAlgo = in_array('xxh3', hash_algos(), true) ? 'xxh3' : 'crc32';
        }

        return self::$traceHashAlgo;
    }
}
