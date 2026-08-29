<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Toolbar\ToolbarItem;

use function is_array;

/**
 * Presents a captured Inertia page and its request negotiation metadata.
 */
final class InertiaPanel implements ToolbarPanelProviderInterface
{
    public function hasContent(array $payload): bool
    {
        $data = self::snapshot($payload)->data();
        $requestHeaders = $data['requestHeaders'] ?? null;

        return is_array($data['page'] ?? null)
            || (is_array($requestHeaders) && isset($requestHeaders['X-Inertia']));
    }

    public function icon(): string
    {
        return 'inertia';
    }
    public function id(): string
    {
        return 'inertia';
    }

    public function name(): string
    {
        return 'Inertia';
    }

    public function render(array $payload): string
    {
        return InertiaPanelRenderer::render(self::snapshot($payload));
    }

    public function toolbarItems(array $payload): array
    {
        $page = self::snapshot($payload)->data()['page'] ?? null;
        $component = is_array($page) ? Coerce::string($page['component'] ?? null) : '';

        return $component === ''
            ? []
            : [new ToolbarItem(value: $component, title: 'Inertia component')];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): InertiaSnapshot
    {
        return InertiaSnapshot::fromArray($payload, '$.panels.inertia');
    }
}
