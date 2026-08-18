<?php

declare(strict_types=1);

namespace Yii3\Debug\Grid;

use Stringable;

use function http_build_query;

/**
 * Builds grid navigation URLs (sort, pager, page size) on top of the current request's path and query parameters.
 *
 * Parameters emitted by the grid override the current values; a `null` value removes the parameter. Every other
 * query parameter — filter groups, the snapshot tag, the theme snapshot — is preserved so grid navigation never
 * drops context.
 */
final readonly class GridUrlCreator
{
    /**
     * @param string $path Path of the current request (for example, `/debug`).
     * @param array<array-key, mixed> $queryParams Parsed query parameters of the current request.
     */
    public function __construct(
        private string $path,
        private array $queryParams,
    ) {}

    /**
     * Returns the URL for the given grid state.
     *
     * @param array<string, bool|float|int|string|Stringable|null> $arguments Path arguments (unused; the debugger
     * routes carry no path params).
     * @param array<array-key, mixed> $queryParameters Grid parameters to merge; `null` removes the parameter.
     */
    public function __invoke(array $arguments, array $queryParameters): string
    {
        $query = $this->queryParams;

        foreach ($queryParameters as $name => $value) {
            if ($value === null) {
                // With a fixed page-size constraint the grid reports `per-page` as "default" (`null`) on every link;
                // keep the current selection (including `all`) instead of dropping it.
                if ($name !== 'per-page') {
                    unset($query[$name]);
                }

                continue;
            }

            $query[$name] = $value;
        }

        if ($query === []) {
            return $this->path;
        }

        return $this->path . '?' . http_build_query($query);
    }
}
