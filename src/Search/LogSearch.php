<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Log\LogRow;

/**
 * Applies the Yii2-compatible `Log[attribute]` filter vocabulary to typed log rows.
 */
final readonly class LogSearch
{
    /**
     * @param array<string, string> $activeFilters Active log filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<LogRow> $rows Captured log rows.
     *
     * @return list<LogRow> Rows matching every active filter.
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
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::LOG));
    }
}
