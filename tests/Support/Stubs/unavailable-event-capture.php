<?php

declare(strict_types=1);

/**
 * Declares the capture helper with a failing trace builder, standing in for a future package version that can no
 * longer read the backtrace.
 *
 * The file is required from a process-isolated test, before the installed package declares the same name.
 */

namespace PHPForge\Debug\Panel\Event;

use RuntimeException;

final class EventCapture
{
    /**
     * @param array<string, string> $fields Adapter-selected fields only.
     *
     * @return array<string, string>
     */
    public static function context(array $fields): array
    {
        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $frames Backtrace acquired with `DEBUG_BACKTRACE_IGNORE_ARGS`.
     * @param int $limit Maximum frames.
     * @param list<string> $skipFiles Adapter instrumentation files to omit.
     *
     * @return list<string>
     */
    public static function trace(array $frames, int $limit, array $skipFiles = []): array
    {
        throw new RuntimeException(
            'The backtrace is unavailable.',
        );
    }
}
