<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Profile\ProfileRow;

/**
 * Filters captured profile rows from the Yii2-compatible `Profile[...]` query group.
 */
final readonly class ProfileSearch
{
    /**
     * @param array<string, string> $activeFilters
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<ProfileRow> $rows
     *
     * @return list<ProfileRow>
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);
        $engine->addCondition('info', $this->activeFilters['info'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::PROFILE));
    }
}
