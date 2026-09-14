<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable JSON-leaf difference counts for one captured panel.
 */
final readonly class HistoryPanelDifferenceCounts
{
    /**
     * @param int $added JSON leaves present only in the target.
     * @param int $removed JSON leaves present only in the baseline.
     * @param int $changed JSON leaves present in both with a different value.
     * @param int $unchanged JSON leaves identical in both.
     */
    public function __construct(public int $added, public int $removed, public int $changed, public int $unchanged) {}

    /**
     * Returns how many leaves differ between both captures.
     *
     * @return int Added, removed, and changed leaves combined; unchanged ones are excluded.
     */
    public function differenceCount(): int
    {
        return $this->added + $this->removed + $this->changed;
    }
}
