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
     * @param array<string, string> $activeFilters Submitted filter values kept for this group, keyed by attribute.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * Keeps only the captures matching every active filter.
     *
     * @param list<HistoryRow> $rows Captured captures to filter.
     *
     * @return list<HistoryRow> Rows matching every active filter, reindexed.
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
        $engine->addCondition('sqlCount', $this->activeFilters['sqlCount'] ?? null);

        return $engine->filter($rows);
    }

    /**
     * Builds the search model from the submitted filter values.
     *
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return self Search model holding the active capture filters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::DEBUG));
    }
}
