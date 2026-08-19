<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;

/**
 * Adds request, theme, and adapter-owned URLs to a Yii3 panel without breaking the legacy render contract.
 *
 * Implementations retain {@see PanelInterface::render()} as their context-free fallback. The Yii3 page renderer calls
 * {@see renderWithContext()} when this optional interface is implemented.
 */
interface ContextAwarePanelInterface extends PanelInterface
{
    /**
     * Renders a decoded collector payload with the current debugger request context.
     *
     * @param array<string, mixed> $payload Decoded collector payload.
     * @param PanelRenderContext $context Immutable panel render context.
     *
     * @return string Panel detail markup.
     */
    public function renderWithContext(array $payload, PanelRenderContext $context): string;
}
