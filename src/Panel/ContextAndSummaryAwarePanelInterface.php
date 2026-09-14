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
     * Renders a captured panel payload with the debugger request context and its request summary.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param RequestSummary $summary Summary of the capture being rendered.
     *
     * @return string Rendered detail content.
     */
    public function renderWithContextAndSummary(
        array $payload,
        PanelRenderContext $context,
        RequestSummary $summary,
    ): string;
}
