<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Profile\ProfileRow;

/**
 * Applies the Yii2-compatible `Profile[category|info]` filters to captured Yii3 profile rows.
 */
final readonly class ProfileSearch
{
    /**
     * @param array<string, string> $activeFilters Active Profiling filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<ProfileRow> $rows Captured profile rows.
     *
     * @return list<ProfileRow> Rows matching every active filter.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);
        $engine->addCondition('info', $this->activeFilters['info'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::PROFILE));
    }
}
