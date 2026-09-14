<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Profiler\{Message, Profiler, ProfilerInterface};

use function array_slice;
use function class_exists;
use function error_reporting;
use function is_float;
use function is_int;
use function memory_get_peak_usage;
use function microtime;
use function usort;

use const E_DEPRECATED;

/**
 * Captures request metrics and completed Yii profiler spans in the canonical Profiling panel payload.
 */
final class ProfilingCollector implements CollectorInterface
{
    /**
     * Last profiler message already consumed, so each read resumes where the previous one stopped.
     */
    private Message|null $messageCursor = null;
    /**
     * Request start, in seconds, shared with the summary so both use one origin.
     */
    private float $start = 0.0;
    /**
     * Whether the collector is capturing for the current request.
     */
    private bool $started = false;

    /**
     * @param ProfilerInterface|null $profiler Application profiler supplying the spans, or `null` when none is
     * configured.
     */
    public function __construct(private readonly ProfilerInterface|null $profiler = null) {}

    /**
     * Encodes the captured spans into the Profiling panel payload.
     *
     * @return array<string, mixed>|null Encoded Profiling panel payload; `null` when the collector never started.
     */
    public function capture(): array|null
    {
        if ($this->started === false) {
            return null;
        }

        $end = microtime(true);

        return ProfilingSnapshot::captureCompleted(memory_get_peak_usage(), $end - $this->start, $this->messages())
            ->jsonSerialize();
    }

    /**
     * Records the request start when the middleware did not already supply it.
     *
     * @param ServerRequestInterface $request Request reaching the debugger middleware.
     */
    public function collectRequest(ServerRequestInterface $request): void
    {
        $start = $request->getServerParams()['REQUEST_TIME_FLOAT'] ?? null;

        $this->collectRequestStart(
            is_float($start) || is_int($start) ? (float) $start : microtime(true),
        );
    }

    /**
     * Uses the request start already resolved by the middleware so summary and profiler timing share one origin.
     *
     * @param float $start Request start, in seconds.
     */
    public function collectRequestStart(float $start): void
    {
        $this->start = $start;
    }

    /**
     * Returns the stable identifier of this collector.
     *
     * @return string Stable ID pairing this collector with its panel.
     */
    public function id(): string
    {
        return 'profiling';
    }

    /**
     * Stops capturing and clears the spans accumulated for the request.
     */
    public function shutdown(): void
    {
        $this->started = false;
        $this->messageCursor = null;
        $this->start = 0.0;
    }

    /**
     * Starts capturing and resets the cursor into the profiler message list.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        $this->start = is_float($start) || is_int($start) ? (float) $start : microtime(true);

        $this->messageCursor = null;

        if ($this->profiler instanceof Profiler) {
            self::loadMessageClass();

            foreach ($this->profiler->getMessages() as $message) {
                $this->messageCursor = $message;
            }
        }

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
     * @return list<array{token: string, category: string, context: array<array-key, mixed>}> Completed messages
     * in begin-timestamp order.
     */
    private function messages(): array
    {
        if (!$this->profiler instanceof Profiler) {
            return [];
        }

        $profilerMessages = $this->profiler->getMessages();

        $messageOffset = 0;

        if ($this->messageCursor !== null) {
            $index = 0;

            foreach ($profilerMessages as $message) {
                if ($message === $this->messageCursor) {
                    $messageOffset = $index + 1;

                    break;
                }

                $index++;
            }
        }

        $messages = [];

        foreach (array_slice($profilerMessages, $messageOffset) as $message) {
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
