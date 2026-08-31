<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Vite\{ViteSectionRenderer, ViteSnapshot, ViteSummary};
use PHPForge\Debug\Toolbar\ToolbarItem;

/**
 * Presents the configuration and build chunks of a captured Vite integration.
 */
final class VitePanel implements ToolbarPanelProviderInterface
{
    public function hasContent(array $payload): bool
    {
        return self::snapshot($payload)->components() !== [];
    }

    public function icon(): string
    {
        return 'brand-javascript';
    }

    public function id(): string
    {
        return 'vite';
    }

    public function name(): string
    {
        return 'Vite';
    }

    public function render(array $payload): string
    {
        return ViteSectionRenderer::render($this->summary($payload));
    }

    public function toolbarItems(array $payload): array
    {
        $summary = $this->summary($payload);

        if ($summary->isEmpty()) {
            return [];
        }

        $count = $summary->count();
        $mode = $summary->modeLabel();

        return [
            new ToolbarItem(
                value: $count === 1 ? $mode : "{$count} components · {$mode}",
                title: 'Vite mode',
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): ViteSnapshot
    {
        return ViteSnapshot::fromArray($payload, '$.panels.vite');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function summary(array $payload): ViteSummary
    {
        return new ViteSummary(self::snapshot($payload)->components());
    }
}
