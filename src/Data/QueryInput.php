<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use PHPForge\Debug\Data\QueryInput as CoreQueryInput;

/**
 * Backward-compatible Yii3 facade for the shared parsed-query reader.
 *
 * New integrations should use {@see CoreQueryInput} directly.
 */
final class QueryInput
{
    /**
     * @param array<array-key, mixed> $query Parsed query parameters.
     *
     * @return array<string, string> Normalized filter values.
     */
    public static function group(array $query, string $prefix): array
    {
        return CoreQueryInput::group($query, $prefix);
    }

    /**
     * @param array<array-key, mixed> $query Parsed query parameters.
     */
    public static function scalar(array $query, string $name): string|null
    {
        return CoreQueryInput::scalar($query, $name);
    }
}
