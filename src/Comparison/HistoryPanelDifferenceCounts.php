<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable JSON-leaf difference counts for one captured panel.
 */
final readonly class HistoryPanelDifferenceCounts
{
    public function __construct(
        public int $added,
        public int $removed,
        public int $changed,
        public int $unchanged,
    ) {}

    public function differenceCount(): int
    {
        return $this->added + $this->removed + $this->changed;
    }
}
