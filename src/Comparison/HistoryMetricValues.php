<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable formatted values and outcome for one request-summary metric.
 */
final readonly class HistoryMetricValues
{
    /**
     * @param string $baseline Formatted baseline value.
     * @param string $target Formatted target value.
     * @param string $delta Formatted difference from baseline to target.
     * @param string $trend Directional CSS vocabulary (`up`, `down`, or `neutral`).
     */
    public function __construct(
        public string $baseline,
        public string $target,
        public string $delta,
        public string $trend,
    ) {}

    public function hasDifference(): bool
    {
        return $this->delta !== 'No change';
    }
}
