<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use PHPForge\Debug\Data\PageSize as CorePageSize;

/**
 * Backward-compatible Yii3 facade for the shared page-size contract.
 *
 * New integrations should use {@see CorePageSize} directly.
 */
final class PageSize
{
    public const int DEFAULT = 50;

    public const int MAX = CorePageSize::MAX;

    public const array OPTIONS = CorePageSize::OPTIONS;

    /**
     * @param positive-int $default Page size used when no value is supplied.
     */
    public static function current(string|null $raw, int $default = self::DEFAULT): string
    {
        return CorePageSize::current($raw, $default);
    }

    /**
     * @param positive-int $default Page size used when no value is supplied or the value is invalid.
     */
    public static function resolve(string|null $raw, int $default = self::DEFAULT): int|null
    {
        return CorePageSize::resolve($raw, $default);
    }

    public static function selectorHtml(string $current): string
    {
        return CorePageSize::selectorHtml($current);
    }
}
