<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use Yiisoft\Profiler\{Message, Profiler, ProfilerInterface};

use function array_slice;
use function class_exists;
use function count;
use function error_reporting;
use function is_float;
use function is_int;
use function is_string;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function trim;
use function usort;

use const E_DEPRECATED;

/**
 * Captures the request processing time and peak memory for the Profiling panel.
 *
 * Anchors the timing on `REQUEST_TIME_FLOAT`, falls back to the collector activation time, and normalizes completed
 * `yiisoft/profiler` messages into the framework-neutral Profiling payload.
 *
 * Usage example:
 *
 * ```php
 * $snapshot = (new \Yii3\Debug\Collector\ProfilingCollector())->capture();
 * ```
 */
final class ProfilingCollector implements CollectorInterface
{
    private bool $active = false;
    private int $beginMemory = 0;
    private int $messageOffset = 0;
    private float $start = 0.0;

    /**
     * @param ProfilerInterface|null $profiler Yii profiler source, or `null` for the legacy metrics-only mode.
     */
    public function __construct(private readonly ProfilerInterface|null $profiler = null) {}

    /**
     * Snapshots the elapsed processing time and the peak memory usage.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $collector->capture();
     * ```
     *
     * @return ProfilingSnapshot|null Captured profiling payload; `null` when the collector never started.
     */
    public function capture(): ProfilingSnapshot|null
    {
        if (!$this->active) {
            return null;
        }

        $end = microtime(true);
        $endMemory = memory_get_usage();

        $messages = $this->messages($end, $endMemory);

        return ProfilingSnapshot::captureCompleted(
            memory_get_peak_usage(),
            $end - $this->start,
            $messages,
        );
    }

    /**
     * Returns the stable ID pairing this collector with the Profiling panel.
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
        return 'profiling';
    }

    /**
     * Deactivates the collector, so a reused worker process starts clean.
     */
    public function shutdown(): void
    {
        $this->active = false;
        $this->beginMemory = 0;
        $this->messageOffset = 0;
        $this->start = 0.0;
    }

    /**
     * Activates the collector and anchors the request start timestamp.
     */
    public function startup(): void
    {
        if ($this->active) {
            return;
        }

        if ($this->profiler instanceof Profiler) {
            self::loadMessageClass();
        }

        $this->active = true;
        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $this->start = is_float($start) || is_int($start) ? (float) $start : microtime(true);
        $this->beginMemory = memory_get_usage();
        $this->messageOffset = $this->profiler instanceof Profiler ? count($this->profiler->getMessages()) : 0;
    }

    /**
     * Loads the Yii profiler message DTO without surfacing its PHP 8.4 implicit-nullability deprecation.
     *
     * Version 3.0 remains the latest stable profiler release and supports the adapter's PHP range, but its legacy
     * `string $name = null` declaration is deprecated on newer runtimes. Limiting the mask to class loading keeps
     * application error reporting unchanged while the upstream release remains compatible at runtime.
     */
    private static function loadMessageClass(): void
    {
        $errorReporting = error_reporting();

        try {
            error_reporting($errorReporting & ~E_DEPRECATED);
            class_exists(Message::class);
        } finally {
            error_reporting($errorReporting);
        }
    }

    /**
     * Returns the current request root followed by completed profiler messages ordered by their begin timestamp.
     *
     * @return list<array{token: string, category: string, context: array<array-key, mixed>}>
     */
    private function messages(float $end, int $endMemory): array
    {
        if ($this->profiler === null) {
            return [];
        }

        $messages = [
            [
                'token' => self::requestToken(),
                'category' => 'Yii3\\Application::handle',
                'context' => [
                    'category' => 'Yii3\\Application::handle',
                    'nestedLevel' => 0,
                    'beginTime' => $this->start,
                    'endTime' => $end,
                    'duration' => $end - $this->start,
                    'beginMemory' => $this->beginMemory,
                    'endMemory' => $endMemory,
                    'memoryDiff' => $endMemory - $this->beginMemory,
                    'trace' => [],
                ],
            ],
        ];

        if (!$this->profiler instanceof Profiler) {
            return $messages;
        }

        foreach (array_slice($this->profiler->getMessages(), $this->messageOffset) as $message) {
            $context = $message->context();
            $context['nestedLevel'] = (Coerce::intOrNull($context['nestedLevel'] ?? null) ?? 0) + 1;
            $messages[] = [
                'token' => $message->token(),
                'category' => $message->level(),
                'context' => $context,
            ];
        }

        usort(
            $messages,
            static function (array $left, array $right): int {
                $leftContext = $left['context'];
                $rightContext = $right['context'];
                $leftTime = Coerce::float($leftContext['beginTime'] ?? null);
                $rightTime = Coerce::float($rightContext['beginTime'] ?? null);

                return $leftTime <=> $rightTime;
            },
        );

        return $messages;
    }

    private static function requestToken(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        $token = trim(
            (is_string($method) ? $method : '') . ' ' . (is_string($uri) ? $uri : ''),
        );

        return $token !== '' ? $token : 'Application request';
    }
}
