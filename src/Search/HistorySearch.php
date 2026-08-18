<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\View\History\HistoryRow;
use Yii3\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};

use function array_filter;
use function array_values;
use function in_array;

/**
 * Backs the filter row on the debug index page that lists every captured request manifest entry.
 *
 * Reads the `Debug[attribute]` filter group from the PSR-7 query parameters and applies it to the typed
 * {@see HistoryRow} list with the shared operator grammar: `tag`/`ip`/`url` match as substrings, `method` and
 * `statusCode`/`sqlCount`/`mailCount` match exactly or through a leading `>`/`<` comparison, and `ajax` matches the
 * boolean flag (`1` = AJAX, `0` = regular).
 *
 * Usage example:
 * ```php
 * $search = \Yii3\Debug\Search\HistorySearch::fromQueryParams($request->getQueryParams());
 * $rows = $search->filter($rows);
 * ```
 */
final readonly class HistorySearch
{
    /**
     * HTTP status codes flagged as severe in the request grid.
     */
    public const array CRITICAL_CODES = [400, 404, 500];

    /**
     * @param array<string, string> $activeFilters Active `Debug[attribute]` filter values.
     */
    private function __construct(
        public array $activeFilters,
    ) {}

    /**
     * Applies the active filters to the typed history rows.
     *
     * @param list<HistoryRow> $rows Rows projected from the manifest.
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
        $engine->addCondition('mailCount', $this->activeFilters['mailCount'] ?? null);

        return $engine->filter($rows);
    }

    /**
     * Reads the `Debug[attribute]` filter group from parsed query parameters.
     *
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::DEBUG));
    }

    /**
     * Returns whether the given status code is flagged as critical in {@see CRITICAL_CODES}.
     */
    public function isCodeCritical(int $code): bool
    {
        return in_array($code, self::CRITICAL_CODES, true);
    }
}
