<?php

declare(strict_types=1);

namespace Yii3\Debug\Comparison;

/**
 * Immutable baseline and target capture states for one panel.
 */
final readonly class HistoryPanelStates
{
    public function __construct(public string $baseline, public string $target) {}
}
