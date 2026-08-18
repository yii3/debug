<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * Reads `Prefix[attribute]` filter groups and scalar values from a parsed query-parameter array.
 *
 * Framework-neutral bridge between the request layer (PSR-7 `getQueryParams()`, Yii `getQueryParams()` — both yield
 * the same nested array shape) and the debug search models. Non-string keys, non-scalar values, and empty strings are
 * dropped so the result feeds {@see FilterEngine} directly.
 *
 * Usage example:
 * ```php
 * $filters = \PHPForge\Debug\Data\QueryInput::group($request->getQueryParams(), 'Debug');
 * ```
 */
final class QueryInput
{
    /**
     * Returns the active `Prefix[attribute]` filter map from the query parameters.
     *
     * @param array<array-key, mixed> $query Parsed query parameters.
     * @param string $prefix Filter-group prefix (for example, `Debug` matches `Debug[statusCode]`).
     *
     * @return array<string, string> Attribute-to-value map with empty and non-scalar entries removed.
     */
    public static function group(array $query, string $prefix): array
    {
        $group = $query[$prefix] ?? null;

        if (!is_array($group)) {
            return [];
        }

        $filters = [];

        foreach ($group as $attribute => $value) {
            if (!is_string($attribute)) {
                continue;
            }

            $normalized = self::stringValue($value);

            if ($normalized === null || $normalized === '') {
                continue;
            }

            $filters[$attribute] = $normalized;
        }

        return $filters;
    }

    /**
     * Returns a top-level query parameter as a string, or `null` when absent or non-scalar.
     *
     * @param array<array-key, mixed> $query Parsed query parameters.
     * @param string $name Parameter name to read.
     */
    public static function scalar(array $query, string $name): string|null
    {
        return self::stringValue($query[$name] ?? null);
    }

    private static function stringValue(mixed $value): string|null
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
