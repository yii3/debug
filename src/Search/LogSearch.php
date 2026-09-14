<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Log\LogRow;

/**
 * Applies the Yii2-compatible `Log[attribute]` filters to captured log rows.
 */
final readonly class LogSearch
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
     * Keeps only the messages matching every active filter.
     *
     * @param list<LogRow> $rows Captured messages to filter.
     *
     * @return list<LogRow> Rows matching every active filter, reindexed.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', $this->activeFilters['level'] ?? null);
        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);
        $engine->addCondition('message', $this->activeFilters['message'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * Builds the search model from the submitted filter values.
     *
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return self Search model holding the active message filters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $submitted = QueryInput::group($queryParams, FilterPrefix::LOG);

        $filters = [];

        foreach (['level', 'category', 'message'] as $attribute) {
            if (isset($submitted[$attribute])) {
                $filters[$attribute] = $submitted[$attribute];
            }
        }

        return new self($filters);
    }

    /**
     * Returns the submitted `level` filter value.
     *
     * @return string Submitted `level` filter value, or `''` when the filter is not active.
     */
    public function level(): string
    {
        return $this->activeFilters['level'] ?? '';
    }

    /**
     * Returns the submitted `message` filter value.
     *
     * @return string Submitted `message` filter value, or `''` when the filter is not active.
     */
    public function message(): string
    {
        return $this->activeFilters['message'] ?? '';
    }
}
