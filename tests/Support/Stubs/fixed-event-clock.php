<?php

declare(strict_types=1);

/**
 * Declares a fixed monotonic clock in the collector namespace, so the lifecycle clock reading becomes deterministic.
 *
 * The file is required from a process-isolated test, before the collector first reads the clock.
 */

namespace Yii3\Debug\Collector;

/**
 * Returns a fixed reading of 1.5 seconds, as nanoseconds or as a `[seconds, nanoseconds]` pair like the native function.
 *
 * @return array{int, int}|int
 */
function hrtime(bool $as_number = false): array|int
{
    return $as_number ? 1_500_000_000 : [1, 500_000_000];
}
