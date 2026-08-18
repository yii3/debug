<?php

declare(strict_types=1);

namespace Yii3\Debug\Grid;

use Yii3\Debug\Data\QueryInput;
use Yiisoft\Yii\DataView\Url\{UrlParameterProviderInterface, UrlParameterType};

use function in_array;
use function is_array;

/**
 * Resolves grid URL parameters from PSR-7 query parameters, reading filter values through a `Prefix[attribute]` group.
 *
 * Reserved grid parameters (`page`, `prev-page`, `per-page`, `sort`) are read from the top level; every other name is
 * treated as a filter attribute nested under the configured prefix, so filter inputs named `Prefix[attribute]` round-trip
 * through the shared JavaScript filter bridge unchanged.
 */
final readonly class PrefixedUrlParameterProvider implements UrlParameterProviderInterface
{
    private const array RESERVED = ['page', 'prev-page', 'per-page', 'sort'];

    /**
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     * @param string $prefix Filter-group prefix (for example, `Debug`).
     */
    public function __construct(
        private array $queryParams,
        private string $prefix,
    ) {}

    public function get(string $name, UrlParameterType $type): string|null
    {
        if ($type !== UrlParameterType::Query) {
            return null;
        }

        if (in_array($name, self::RESERVED, true)) {
            return QueryInput::scalar($this->queryParams, $name);
        }

        $group = $this->queryParams[$this->prefix] ?? null;

        if (!is_array($group)) {
            return null;
        }

        return QueryInput::scalar($group, $name);
    }
}
