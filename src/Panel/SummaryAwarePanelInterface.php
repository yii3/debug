<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Storage\RequestSummary;

/**
 * Defines a panel whose detail presentation needs metadata from the captured request summary.
 */
interface SummaryAwarePanelInterface extends ExtensionPanelInterface
{
    /**
     * Renders a captured panel payload with its request summary.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     */
    public function renderWithSummary(array $payload, RequestSummary $summary): string;
}
