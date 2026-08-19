<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Dump\DumpRow;

/**
 * Applies the Yii2-compatible `Log[category|message]` filters to captured dump rows.
 */
final readonly class DumpSearch
{
    /**
     * @param array<string, string> $activeFilters Active dump filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<DumpRow> $rows
     *
     * @return list<DumpRow>
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);
        $engine->addCondition('message', $this->activeFilters['message'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::LOG));
    }
}
