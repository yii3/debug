<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Db\QueryRow;

/**
 * Applies the Yii2-compatible `Db[attribute]` filter vocabulary to typed database-query rows.
 */
final readonly class DbSearch
{
    /**
     * @param array<string, string> $activeFilters Active database filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<QueryRow> $rows Captured query rows.
     *
     * @return list<QueryRow> Rows matching every active filter.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('type', $this->activeFilters['type'] ?? null, partial: true);
        $engine->addCondition('query', $this->activeFilters['query'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::DB));
    }
}
