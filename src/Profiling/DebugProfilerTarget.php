<?php

declare(strict_types=1);

namespace Yii3\Debug\Profiling;

use Yiisoft\Profiler\Message;
use Yiisoft\Profiler\Target\AbstractTarget;

/**
 * Accumulates the profiler messages dispatched to targets in memory for the current request.
 *
 * `Yiisoft\Profiler\Profiler::flush()` moves its messages out before dispatching them, so a flush performed while the
 * capture is still deferred would otherwise leave the Profiling panel empty.
 */
final class DebugProfilerTarget extends AbstractTarget
{
    /**
     * @var list<Message> Messages exported since the last reset.
     */
    private array $captured = [];

    /**
     * Accumulates the exported messages, so every flush of the request reaches the capture.
     *
     * @param Message[] $messages Profiling messages exported by the profiler.
     */
    public function export(array $messages): void
    {
        foreach ($messages as $message) {
            $this->captured[] = $message;
        }
    }

    /**
     * Returns the messages accumulated for the current request.
     *
     * @return list<Message> Messages accumulated in export order.
     */
    public function messages(): array
    {
        return $this->captured;
    }

    /**
     * Clears the messages accumulated for the previous request.
     */
    public function reset(): void
    {
        $this->captured = [];
    }
}
