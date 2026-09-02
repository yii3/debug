<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;

/**
 * Adds request, theme, and adapter-owned URLs to a panel without changing the context-free render contract.
 */
interface ContextAwarePanelInterface extends ExtensionPanelInterface
{
    /**
     * Renders a captured panel payload with the current debugger request context.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     */
    public function renderWithContext(array $payload, PanelRenderContext $context): string;
}
