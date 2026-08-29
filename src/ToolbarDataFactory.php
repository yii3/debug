<?php

declare(strict_types=1);

namespace Yii3\Debug;

use InvalidArgumentException;
use PHPForge\Debug\Storage\{DebugSnapshot, ExceptionSnapshot};
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use Throwable;
use Yii3\Debug\Panel\{ExtensionPanelInterface, ToolbarPanelProviderInterface};
use Yiisoft\Assets\AssetManager;

use function array_is_list;
use function array_key_exists;
use function rawurlencode;
use function rtrim;
use function strlen;
use function substr;
use function trim;

use const PHP_VERSION;

/**
 * Creates toolbar data from framework metadata and captured extension panels.
 */
final readonly class ToolbarDataFactory
{
    /**
     * @var array<string, ExtensionPanelInterface>
     */
    private array $extensionPanels;
    private string $routePrefix;

    /**
     * @param iterable<ExtensionPanelInterface> $extensionPanels Optional extension presenters in toolbar order.
     */
    public function __construct(
        private AssetManager $assetManager,
        string $routePrefix = '/debug',
        private string $position = 'bottom',
        private int $height = 50,
        iterable $extensionPanels = [],
    ) {
        $this->routePrefix = rtrim($routePrefix, '/');
        $panels = [];

        foreach ($extensionPanels as $panel) {
            $id = trim($panel->id());

            if ($id === '') {
                throw new InvalidArgumentException(
                    'Debug toolbar extension panel ID must not be empty.',
                );
            }

            if (isset($panels[$id])) {
                throw new InvalidArgumentException(
                    "Duplicate debug toolbar extension panel ID: {$id}.",
                );
            }

            $panels[$id] = $panel;
        }

        $this->extensionPanels = $panels;
    }

    public function create(string $tag): ToolbarData
    {
        $logo = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/yii.svg');
        $iconBaseUrl = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/ajax.svg');

        $iconBaseUrl = substr($iconBaseUrl, 0, -strlen('ajax.svg'));

        return ToolbarData::create($tag, 'Yii Debugger')
            ->withNavigation(
                $this->routePrefix,
                $this->routePrefix . '/view?tag=' . rawurlencode($tag) . '&panel=config',
                $this->routePrefix . '/php-info',
            )
            ->withPresentation($this->position, $this->height, $iconBaseUrl)
            ->withBranding($logo, $logo, PHP_VERSION, '3');
    }

    public function createForSnapshot(DebugSnapshot $snapshot): ToolbarData
    {
        return $this->create($snapshot->summary->tag)
            ->withPanels($this->panels($snapshot->summary->tag, $snapshot));
    }

    public function withPresentation(string $position, int $height): self
    {
        return new self(
            $this->assetManager,
            $this->routePrefix,
            $position,
            $height,
            $this->extensionPanels,
        );
    }

    public function withRoutePrefix(string $routePrefix): self
    {
        return new self(
            $this->assetManager,
            $routePrefix,
            $this->position,
            $this->height,
            $this->extensionPanels,
        );
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private static function assertToolbarItems(string $panelId, array $items): void
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                "Debug toolbar extension panel {$panelId} must return a list of items.",
            );
        }

        foreach ($items as $item) {
            if (!$item instanceof ToolbarItem) {
                throw new InvalidArgumentException(
                    "Debug toolbar extension panel {$panelId} must return only ToolbarItem instances.",
                );
            }
        }
    }

    /**
     * @return list<ToolbarPanel>
     */
    private function panels(string $tag, DebugSnapshot $snapshot): array
    {
        $toolbarPanels = [];

        foreach ($this->extensionPanels as $id => $panel) {
            $url = $this->viewUrl($tag, $id);
            $failure = $snapshot->failures[$id] ?? null;

            if ($failure !== null) {
                $toolbarPanels[] = new ToolbarPanel(
                    id: $id,
                    title: $panel->name(),
                    url: $url,
                    items: [
                        new ToolbarItem(
                            value: 'error',
                            label: $panel->name(),
                            status: 'danger',
                            title: $failure->exception->getMessage(),
                        ),
                    ],
                );

                continue;
            }

            if (!array_key_exists($id, $snapshot->panels)) {
                continue;
            }

            if (!$panel instanceof ToolbarPanelProviderInterface) {
                continue;
            }

            try {
                $items = $panel->toolbarItems($snapshot->panels[$id]);
                self::assertToolbarItems($id, $items);
            } catch (Throwable $throwable) {
                $toolbarPanels[] = new ToolbarPanel(
                    id: $id,
                    title: $panel->name(),
                    url: $url,
                    items: [
                        new ToolbarItem(
                            value: 'error',
                            label: $panel->name(),
                            status: 'danger',
                            title: ExceptionSnapshot::fromThrowable($throwable)->getMessage(),
                        ),
                    ],
                );

                continue;
            }

            if ($items === []) {
                continue;
            }

            $toolbarPanels[] = new ToolbarPanel(
                id: $id,
                title: $panel->name(),
                url: $url,
                icon: $panel->icon(),
                items: $items,
            );
        }

        return $toolbarPanels;
    }

    private function viewUrl(string $tag, string $panelId): string
    {
        return "{$this->routePrefix}/view?tag=" . rawurlencode($tag) . '&panel=' . rawurlencode($panelId);
    }
}
