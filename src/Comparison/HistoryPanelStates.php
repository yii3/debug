<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable baseline and target capture states for one panel.
 */
final readonly class HistoryPanelStates
{
    /**
     * @param string $baseline State of the panel in the baseline capture.
     * @param string $target State of the panel in the target capture.
     */
    public function __construct(public string $baseline, public string $target) {}
}
