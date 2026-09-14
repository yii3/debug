<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Profile\ProfileRow;

use function trim;

/**
 * Filters captured profile rows for the unified Profiling timeline and table.
 */
final readonly class ProfileSearch
{
    /**
     * @param array<string, string> $activeFilters Submitted filter values kept for this group, keyed by attribute.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * Returns the submitted `category` filter value.
     *
     * @return string Submitted `category` filter value, or `''` when the filter is not active.
     */
    public function category(): string
    {
        return $this->activeFilters['category'] ?? '';
    }

    /**
     * Returns the submitted `duration` filter value.
     *
     * @return string Submitted `duration` filter value, or `''` when the filter is not active.
     */
    public function duration(): string
    {
        return $this->activeFilters['duration'] ?? '';
    }

    /**
     * Keeps only the spans matching every active filter.
     *
     * @param list<ProfileRow> $rows Captured spans to filter.
     *
     * @return list<ProfileRow> Rows matching every active filter, reindexed.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);
        $engine->addCondition('info', $this->activeFilters['info'] ?? null, partial: true);

        if (isset($this->activeFilters['duration'])) {
            $engine->addMinimumCondition('duration', (float) $this->activeFilters['duration']);
        }

        return $engine->filter($rows);
    }

    /**
     * Builds the search model from the submitted filter values.
     *
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return self Search model holding the active span filters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $submitted = QueryInput::group($queryParams, FilterPrefix::PROFILE);

        $filters = [];

        $duration = trim($submitted['duration'] ?? '');

        if (QueryInput::minimumBound($duration) !== null) {
            $filters['duration'] = $duration;
        }

        foreach (['category', 'info'] as $attribute) {
            if (isset($submitted[$attribute])) {
                $filters[$attribute] = $submitted[$attribute];
            }
        }

        return new self($filters);
    }

    /**
     * Returns the submitted `info` filter value.
     *
     * @return string Submitted `info` filter value, or `''` when the filter is not active.
     */
    public function info(): string
    {
        return $this->activeFilters['info'] ?? '';
    }
}
