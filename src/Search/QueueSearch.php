<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Queue\JobRecord;

/**
 * Applies the Yii2-compatible `Queue[attribute]` filters to captured queue records.
 */
final readonly class QueueSearch
{
    /**
     * @param array<string, string> $activeFilters Active queue filter values.
     */
    private function __construct(public array $activeFilters) {}

    /**
     * @param list<JobRecord> $rows
     *
     * @return list<JobRecord>
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('eventType', $this->activeFilters['eventType'] ?? null);
        $engine->addCondition('componentId', $this->activeFilters['componentId'] ?? null);
        $engine->addCondition('driverName', $this->activeFilters['driverName'] ?? null, partial: true);
        $engine->addCondition('jobClass', $this->activeFilters['jobClass'] ?? null, partial: true);
        $engine->addCondition('jobId', $this->activeFilters['jobId'] ?? null, partial: true);

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        return new self(QueryInput::group($queryParams, FilterPrefix::QUEUE));
    }
}
