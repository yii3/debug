<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Event\EventRow;

/**
 * Applies the Yii2-compatible `Event[attribute]` filters to captured event rows.
 */
final readonly class EventSearch
{
    /**
     * @param array<string, string> $activeFilters Submitted filter values kept for this group, keyed by attribute.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * Returns the submitted `class` filter value.
     *
     * @return string Submitted `class` filter value, or `''` when the filter is not active.
     */
    public function class(): string
    {
        return $this->activeFilters['class'] ?? '';
    }

    /**
     * Keeps only the events matching every active filter.
     *
     * @param list<EventRow> $rows Captured events to filter.
     *
     * @return list<EventRow> Rows matching every active filter, reindexed.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('name', $this->activeFilters['name'] ?? null, partial: true);
        $engine->addCondition('class', $this->activeFilters['class'] ?? null, partial: true);
        $engine->addCondition('senderClass', $this->activeFilters['senderClass'] ?? null, partial: true);
        $engine->addCondition('isStatic', $this->activeFilters['isStatic'] ?? null);

        return $engine->filter($rows);
    }

    /**
     * Builds the search model from the submitted filter values.
     *
     * @param array<array-key, mixed> $queryParams Raw query parameters of the debugger request.
     *
     * @return self Search model holding the active event filters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $submitted = QueryInput::group($queryParams, FilterPrefix::EVENT);

        $filters = [];

        foreach (['name', 'class', 'senderClass'] as $attribute) {
            if (isset($submitted[$attribute])) {
                $filters[$attribute] = $submitted[$attribute];
            }
        }

        $isStatic = $submitted['isStatic'] ?? null;

        if ($isStatic === '0' || $isStatic === '1') {
            $filters['isStatic'] = $isStatic;
        }

        return new self($filters);
    }

    /**
     * Returns the submitted `isStatic` filter value.
     *
     * @return string Submitted `isStatic` filter value, or `''` when the filter is not active.
     */
    public function isStatic(): string
    {
        return $this->activeFilters['isStatic'] ?? '';
    }

    /**
     * Returns the submitted `name` filter value.
     *
     * @return string Submitted `name` filter value, or `''` when the filter is not active.
     */
    public function name(): string
    {
        return $this->activeFilters['name'] ?? '';
    }

    /**
     * Returns the submitted `senderClass` filter value.
     *
     * @return string Submitted `senderClass` filter value, or `''` when the filter is not active.
     */
    public function senderClass(): string
    {
        return $this->activeFilters['senderClass'] ?? '';
    }
}
