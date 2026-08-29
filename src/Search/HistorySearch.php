<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\View\History\HistoryRow;

use function array_filter;
use function array_values;

/**
 * Filters captured request rows from the `Debug[...]` query group.
 */
final readonly class HistorySearch
{
    /**
     * @param array<string, string> $activeFilters
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<HistoryRow> $rows
     *
     * @return list<HistoryRow>
     */
    public function filter(array $rows): array
    {
        $ajax = $this->activeFilters['ajax'] ?? null;

        if ($ajax === '0' || $ajax === '1') {
            $expected = $ajax === '1';
            $rows = array_values(
                array_filter($rows, static fn(HistoryRow $row): bool => $row->ajax === $expected),
            );
        }

        $engine = new FilterEngine();

        $engine->addCondition('tag', $this->activeFilters['tag'] ?? null, partial: true);
        $engine->addCondition('ip', $this->activeFilters['ip'] ?? null, partial: true);
        $engine->addCondition('url', $this->activeFilters['url'] ?? null, partial: true);
        $engine->addCondition('method', $this->activeFilters['method'] ?? null);
        $engine->addCondition('statusCode', $this->activeFilters['statusCode'] ?? null);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::DEBUG));
    }
}
