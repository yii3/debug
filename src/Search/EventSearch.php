<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Event\EventRow;

/**
 * Applies the Yii2-compatible `Event[attribute]` filter vocabulary to typed event rows.
 */
final readonly class EventSearch
{
    /**
     * @param array<string, string> $activeFilters Active event filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<EventRow> $rows Captured event rows.
     *
     * @return list<EventRow> Rows matching every active filter.
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('isStatic', $this->activeFilters['isStatic'] ?? null);
        $engine->addCondition('name', $this->activeFilters['name'] ?? null, partial: true);
        $engine->addCondition('class', $this->activeFilters['class'] ?? null, partial: true);
        $engine->addCondition('senderClass', $this->activeFilters['senderClass'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::EVENT));
    }
}
