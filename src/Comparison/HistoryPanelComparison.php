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
     */
    public function __construct(
        public string $id,
        public string $label,
        private HistoryPanelStates $states,
        private HistoryPanelDifferenceCounts $counts,
    ) {}

    public function added(): int
    {
        return $this->counts->added;
    }

    public function baselineState(): string
    {
        return $this->states->baseline;
    }

    public function changed(): int
    {
        return $this->counts->changed;
    }

    public function differenceCount(): int
    {
        return $this->counts->differenceCount();
    }

    public function removed(): int
    {
        return $this->counts->removed;
    }

    public function targetState(): string
    {
        return $this->states->target;
    }

    public function unchanged(): int
    {
        return $this->counts->unchanged;
    }

    public function withDifferenceCounts(HistoryPanelDifferenceCounts $counts): self
    {
        return new self(
            $this->id,
            $this->label,
            $this->states,
            $counts,
        );
    }

    public function withStates(HistoryPanelStates $states): self
    {
        return new self(
            $this->id,
            $this->label,
            $states,
            $this->counts,
        );
    }
}
