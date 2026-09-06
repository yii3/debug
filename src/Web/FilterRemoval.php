<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use PHPForge\Debug\Data\QueryInput;

/**
 * Removes selected filters and resets pagination without changing unrelated navigation parameters.
 */
final class FilterRemoval
{
    /**
     * @param array<array-key, mixed> $queryParams Query parameters already normalized by the panel.
     * @param string $prefix Filter group to update.
     * @param list<string> $without Attributes to remove from that group.
     *
     * @return array<array-key, mixed>
     */
    public static function queryParams(array $queryParams, string $prefix, array $without): array
    {
        $filters = QueryInput::group($queryParams, $prefix);

        foreach ($without as $attribute) {
            unset($filters[$attribute]);
        }

        if ($filters === []) {
            unset($queryParams[$prefix]);
        } else {
            $queryParams[$prefix] = $filters;
        }

        unset($queryParams['page']);

        return $queryParams;
    }
}
