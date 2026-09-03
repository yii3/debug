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
     * @param array<string, string> $activeFilters
     */
    private function __construct(public array $activeFilters) {}

    public function class(): string
    {
        return $this->activeFilters['class'] ?? '';
    }

    /**
     * @param list<EventRow> $rows
     *
     * @return list<EventRow>
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
     * @param array<array-key, mixed> $queryParams
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

    public function isStatic(): string
    {
        return $this->activeFilters['isStatic'] ?? '';
    }

    public function name(): string
    {
        return $this->activeFilters['name'] ?? '';
    }

    public function senderClass(): string
    {
        return $this->activeFilters['senderClass'] ?? '';
    }
}
