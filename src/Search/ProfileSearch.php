<?php

declare(strict_types=1);

namespace Yii3\Debug\Search;

use PHPForge\Debug\Data\{FilterEngine, FilterPrefix, QueryInput};
use PHPForge\Debug\Panel\Profile\ProfileRow;

use function is_finite;
use function is_numeric;
use function trim;

/**
 * Filters captured profile rows for the unified Profiling timeline and table.
 */
final readonly class ProfileSearch
{
    /**
     * @param array<string, string> $activeFilters
     */
    private function __construct(public array $activeFilters) {}

    public function category(): string
    {
        return $this->activeFilters['category'] ?? '';
    }

    public function duration(): string
    {
        return $this->activeFilters['duration'] ?? '';
    }

    /**
     * @param list<ProfileRow> $rows
     *
     * @return list<ProfileRow>
     */
    public function filter(array $rows): array
    {
        $engine = new FilterEngine();

        $engine->addCondition('category', $this->activeFilters['category'] ?? null, partial: true);
        $engine->addCondition('info', $this->activeFilters['info'] ?? null, partial: true);

        if (isset($this->activeFilters['duration'])) {
            $engine->addMinimumCondition('duration', (float) $this->activeFilters['duration']);
        }

        return $engine->filter($rows);
    }

    /**
     * @param array<array-key, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $submitted = QueryInput::group($queryParams, FilterPrefix::PROFILE);

        $filters = [];

        $duration = trim($submitted['duration'] ?? '');

        if ($duration !== '' && is_numeric($duration)) {
            $minimum = (float) $duration;

            if (is_finite($minimum) && $minimum >= 0.0) {
                $filters['duration'] = $duration;
            }
        }

        foreach (['category', 'info'] as $attribute) {
            if (isset($submitted[$attribute])) {
                $filters[$attribute] = $submitted[$attribute];
            }
        }

        return new self($filters);
    }

    public function info(): string
    {
        return $this->activeFilters['info'] ?? '';
    }
}
