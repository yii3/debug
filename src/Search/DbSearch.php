<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Db\QueryRow;

/**
 * Applies the Yii2-compatible Database type and SQL substring filters.
 */
final readonly class DbSearch
{
    /**
     * @param array<string, string> $activeFilters Submitted filter values kept for this group, keyed by attribute.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * Keeps only the statements matching every active filter.
     *
     * @param list<QueryRow> $rows Captured statements to filter.
     *
     * @return list<QueryRow> Rows matching every active filter, reindexed.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('type', $this->activeFilters['type'] ?? null, partial: true);
        $engine->addCondition('query', $this->activeFilters['query'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * Builds the search model from the submitted filter values.
     *
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return self Search model holding the active statement filters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $submitted = QueryInput::group($queryParams, FilterPrefix::DB);

        $filters = [];

        foreach (['type', 'query'] as $attribute) {
            if (isset($submitted[$attribute])) {
                $filters[$attribute] = $submitted[$attribute];
            }
        }

        return new self($filters);
    }
}
