<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable structural-difference summary for one panel across two captured requests.
 *
 * Counts describe JSON leaf paths only. Captured values are deliberately excluded so the comparison overview cannot
 * reveal data that the individual panels keep behind their own presentation and redaction rules.
 */
final readonly class HistoryPanelComparison
{
    /**
     * @param string $id Stable panel ID.
     * @param string $label Panel display name.
     * @param HistoryPanelStates $states Panel state on each side of the comparison.
     * @param HistoryPanelDifferenceCounts $counts JSON-leaf difference counts.
     */
    public function __construct(
        public string $id,
        public string $label,
        private HistoryPanelStates $states,
        private HistoryPanelDifferenceCounts $counts,
    ) {}

    /**
     * Returns the leaves present only in the target capture.
     *
     * @return int JSON leaves present only in the target.
     */
    public function added(): int
    {
        return $this->counts->added;
    }

    /**
     * Returns the panel state in the baseline capture.
     *
     * @return string State of the panel in the baseline capture.
     */
    public function baselineState(): string
    {
        return $this->states->baseline;
    }

    /**
     * Returns the leaves whose value differs between captures.
     *
     * @return int JSON leaves present in both with a different value.
     */
    public function changed(): int
    {
        return $this->counts->changed;
    }

    /**
     * Returns how many leaves differ between both captures.
     *
     * @return int Added, removed, and changed leaves combined; unchanged ones are excluded.
     */
    public function differenceCount(): int
    {
        return $this->counts->differenceCount();
    }

    /**
     * Returns the leaves present only in the baseline capture.
     *
     * @return int JSON leaves present only in the baseline.
     */
    public function removed(): int
    {
        return $this->counts->removed;
    }

    /**
     * Returns the panel state in the target capture.
     *
     * @return string State of the panel in the target capture.
     */
    public function targetState(): string
    {
        return $this->states->target;
    }

    /**
     * Returns the leaves identical in both captures.
     *
     * @return int JSON leaves identical in both captures.
     */
    public function unchanged(): int
    {
        return $this->counts->unchanged;
    }

    /**
     * Returns a copy carrying different JSON-leaf counts.
     *
     * @param HistoryPanelDifferenceCounts $counts Counts to apply.
     *
     * @return self Comparison with the counts applied.
     */
    public function withDifferenceCounts(HistoryPanelDifferenceCounts $counts): self
    {
        return new self($this->id, $this->label, $this->states, $counts);
    }

    /**
     * Returns a copy carrying different panel states.
     *
     * @param HistoryPanelStates $states States to apply.
     *
     * @return self Comparison with the states applied.
     */
    public function withStates(HistoryPanelStates $states): self
    {
        return new self($this->id, $this->label, $states, $this->counts);
    }
}
