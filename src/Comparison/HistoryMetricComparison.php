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
     * @param HistoryMetricValues $values Formatted values and outcome of the metric.
     * @param string|null $panelId Related panel ID used for deep links, when applicable.
     */
    private function __construct(
        public string $label,
        private HistoryMetricValues $values,
        private string|null $panelId = null,
    ) {}

    /**
     * Returns the formatted value of the baseline capture.
     *
     * @return string Formatted baseline value.
     */
    public function baseline(): string
    {
        return $this->values->baseline;
    }

    /**
     * Creates a metric that can be fluently linked with {@see withPanelId()}.
     *
     * @param string $label Human-readable metric name.
     * @param HistoryMetricValues $values Formatted values and outcome of the metric.
     *
     * @return self Metric without a panel link.
     */
    public static function create(string $label, HistoryMetricValues $values): self
    {
        return new self($label, $values);
    }

    /**
     * Returns the formatted difference between both captures.
     *
     * @return string Formatted difference from baseline to target.
     */
    public function delta(): string
    {
        return $this->values->delta;
    }

    /**
     * Returns the panel this metric deep-links to.
     *
     * @return string|null Panel the metric deep-links to, or `null` when it links nowhere.
     */
    public function panelId(): string|null
    {
        return $this->panelId;
    }

    /**
     * Returns the formatted value of the target capture.
     *
     * @return string Formatted target value.
     */
    public function target(): string
    {
        return $this->values->target;
    }

    /**
     * Returns the direction the metric moved in.
     *
     * @return string Directional CSS vocabulary: `'up'`, `'down'`, or `'neutral'`.
     */
    public function trend(): string
    {
        return $this->values->trend;
    }

    /**
     * Returns a copy deep-linking the metric to a panel.
     *
     * @param string|null $panelId Panel to link to, or `null` to drop the link.
     *
     * @return self Metric with the link applied.
     */
    public function withPanelId(string|null $panelId): self
    {
        return new self($this->label, $this->values, $panelId);
    }
}
