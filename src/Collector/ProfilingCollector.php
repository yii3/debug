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
use function memory_get_peak_usage;
use function microtime;
use function usort;

use const E_DEPRECATED;

/**
 * Captures request metrics and completed Yii profiler blocks in the canonical Profiling panel payload.
 */
final class ProfilingCollector implements CollectorInterface
{
    private int $messageOffset = 0;
    private float $start = 0.0;
    private bool $started = false;

    public function __construct(private readonly ProfilerInterface|null $profiler = null) {}

    public function capture(): ProfilingSnapshot|null
    {
        if ($this->started === false) {
            return null;
        }

        $end = microtime(true);

        return ProfilingSnapshot::captureCompleted(
            memory_get_peak_usage(),
            $end - $this->start,
            $this->messages(),
        );
    }

    public function id(): string
    {
        return 'profiling';
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->messageOffset = 0;
        $this->start = 0.0;
    }

    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        if ($this->profiler instanceof Profiler) {
            self::loadMessageClass();
        }

        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $this->start = is_float($start) || is_int($start) ? (float) $start : microtime(true);

        $this->messageOffset = $this->profiler instanceof Profiler ? count($this->profiler->getMessages()) : 0;
        $this->started = true;
    }

    /**
     * Loads the Yii profiler message DTO without surfacing its PHP 8.4 implicit-nullability deprecation.
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
     * Returns completed profiler messages ordered by their begin timestamp.
     *
     * @return list<array{token: string, category: string, context: array<array-key, mixed>}>
     */
    private function messages(): array
    {
        if (!$this->profiler instanceof Profiler) {
            return [];
        }

        $messages = [];

        foreach (array_slice($this->profiler->getMessages(), $this->messageOffset) as $message) {
            $context = $message->context();
            $messages[] = [
                'token' => $message->token(),
                'category' => $message->level(),
                'context' => $context,
            ];
        }

        usort(
            $messages,
            static function (array $left, array $right): int {
                $leftTime = Coerce::float($left['context']['beginTime'] ?? null);
                $rightTime = Coerce::float($right['context']['beginTime'] ?? null);

                return $leftTime <=> $rightTime;
            },
        );

        return $messages;
    }
}
