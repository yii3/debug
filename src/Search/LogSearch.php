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
     * @param array<string, string> $activeFilters
     */
    private function __construct(public array $activeFilters) {}

    public function category(): string
    {
        return $this->activeFilters['category'] ?? '';
    }

    /**
     * @param list<LogRow> $rows
     *
     * @return list<LogRow>
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
     * @param array<array-key, mixed> $queryParams
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

    public function level(): string
    {
        return $this->activeFilters['level'] ?? '';
    }

    public function message(): string
    {
        return $this->activeFilters['message'] ?? '';
    }
}
