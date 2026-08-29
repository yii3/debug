<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable presentation data for one request-summary metric in a capture comparison.
 */
final readonly class HistoryMetricComparison
{
    /**
     * @param string $label Human-readable metric name.
     * @param string|null $panelId Related panel ID used for deep links, when applicable.
     */
    public function __construct(
        public string $label,
        private HistoryMetricValues $values,
        private string|null $panelId = null,
    ) {}

    public function baseline(): string
    {
        return $this->values->baseline;
    }

    public function delta(): string
    {
        return $this->values->delta;
    }

    public function hasDifference(): bool
    {
        return $this->values->hasDifference();
    }

    public function panelId(): string|null
    {
        return $this->panelId;
    }

    public function target(): string
    {
        return $this->values->target;
    }

    public function trend(): string
    {
        return $this->values->trend;
    }

    public function withPanelId(string|null $panelId): self
    {
        return new self(
            $this->label,
            $this->values,
            $panelId,
        );
    }
}
