<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\RequestSummary;

/**
 * Defines a panel whose detail view composes request metadata with query and URL context.
 */
interface ContextAndSummaryAwarePanelInterface extends ExtensionPanelInterface
{
    /**
     * @param array<string, mixed> $payload Serialized panel payload.
     */
    public function renderWithContextAndSummary(
        array $payload,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string;
}
