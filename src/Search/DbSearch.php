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
     * @param array<string, string> $activeFilters
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<QueryRow> $rows
     * @return list<QueryRow>
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('type', $this->activeFilters['type'] ?? null, partial: true);
        $engine->addCondition('query', $this->activeFilters['query'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
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
