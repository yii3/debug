<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Profile\ProfileRow;

/**
 * Applies the Yii2-compatible minimum-duration and category filters to Timeline profile spans.
 */
final readonly class TimelineSearch
{
    /**
     * @param array<string, string> $activeFilters Active Timeline filter values.
     */
    private function __construct(public array $activeFilters) {}

    public function category(): string
    {
        return $this->activeFilters['category'] ?? '';
    }

    public function duration(): string
    {
        return $this->activeFilters['duration'] ?? '';
    }

    /**
     * @param list<ProfileRow> $rows Captured profile rows.
     *
     * @return list<ProfileRow> Rows matching every active filter.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);

        if (isset($this->activeFilters['duration'])) {
            $engine->addMinimumCondition('duration', (float) $this->activeFilters['duration']);
        }

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::TIMELINE));
    }
}
