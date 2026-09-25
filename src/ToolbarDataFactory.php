<?php

declare(strict_types=1);

namespace Yii3\Debug;

use InvalidArgumentException;
use PHPForge\Debug\Data\FilterPrefix;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Storage\{DebugSnapshot, ExceptionSnapshot};
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use PHPForge\Debug\View\ViewMessage;
use Throwable;
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yii3\Debug\Panel\{
    BuiltInPanels,
    CaptureToolbarProviderInterface,
    ExtensionPanelInterface,
    LogPanel,
    PanelContent,
    ToolbarPanelProviderInterface,
    ToolbarTitleProviderInterface,
};
use Yii3\Debug\Web\DebugUrlGenerator;
use Yiisoft\Assets\AssetManager;

use function array_is_list;
use function array_key_exists;
use function count;
use function rawurlencode;
use function strlen;
use function substr;
use function trim;

use const PHP_VERSION;

/**
 * Creates toolbar data from framework metadata and captured extension panels.
 */
final class ToolbarDataFactory
{
    /**
     * @var array<string, ExtensionPanelInterface>
     */
    private array $extensionPanels = [];

    /**
     * @param AssetManager $assetManager Manager resolving the published URLs of the toolbar assets.
     * @param ToolbarOptions $options Settings carrying the route prefix every debugger URL is built on and the toolbar
     * presentation.
     */
    public function __construct(
        private readonly AssetManager $assetManager,
        private readonly ToolbarOptions $options = new ToolbarOptions(),
    ) {}

    /**
     * Builds the toolbar chrome for a capture, without its panels.
     *
     * @param string $tag Tag of the capture the toolbar links to.
     *
     * @return ToolbarData Toolbar payload carrying navigation, presentation, and branding.
     */
    public function create(string $tag): ToolbarData
    {
        $logo = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/yii.svg');
        $iconBaseUrl = $this->assetManager->getUrl(ToolbarAsset::class, 'svg/ajax.svg');

        $iconBaseUrl = substr($iconBaseUrl, 0, -strlen('ajax.svg'));

        return ToolbarData::create($tag, ViewMessage::TITLE->value)
            ->withNavigation(
                $this->options->routePrefix,
                "{$this->options->routePrefix}/view?tag=" . rawurlencode($tag) . '&panel=config',
                "{$this->options->routePrefix}/php-info",
            )
            ->withPresentation($this->options->position, $this->options->height, $iconBaseUrl)
            ->withBranding($logo, $logo, PHP_VERSION, '3');
    }

    /**
     * Builds the complete toolbar payload for a capture, panels included.
     *
     * @param DebugSnapshot $snapshot Capture the toolbar describes.
     *
     * @return ToolbarData Toolbar payload including every registered extension panel.
     */
    public function createForSnapshot(DebugSnapshot $snapshot): ToolbarData
    {
        return $this->create($snapshot->summary->tag)
            ->withPanels($this->panels($snapshot->summary->tag, $snapshot));
    }

    /**
     * Returns a copy carrying the extension panels shown on the toolbar.
     *
     * Chips follow the order given, which is the order the sidebar lists the same panels in: the container passes
     * {@see ExtensionRegistry::panelsWithBuiltIns()}, built-ins first in {@see BuiltInPanels::IDS} order.
     *
     * @param iterable<ExtensionPanelInterface> $extensionPanels Optional extension presenters in toolbar order.
     *
     * @throws InvalidArgumentException when a panel ID is empty or duplicated.
     *
     * @return self Factory with the panels applied.
     */
    public function withExtensionPanels(iterable $extensionPanels): self
    {
        $panels = [];

        foreach ($extensionPanels as $panel) {
            $id = trim($panel->id());

            if ($id === '') {
                throw new InvalidArgumentException(
                    Message::TOOLBAR_PANEL_ID_EMPTY->getMessage(),
                );
            }

            if (isset($panels[$id])) {
                throw new InvalidArgumentException(
                    Message::TOOLBAR_PANEL_ID_DUPLICATE->getMessage($id),
                );
            }

            $panels[$id] = $panel;
        }

        $new = clone $this;
        $new->extensionPanels = $panels;

        return $new;
    }

    /**
     * Rejects a toolbar payload whose items are not a plain list of {@see ToolbarItem}.
     *
     * @param string $panelId Panel that produced the items.
     * @param array<array-key, mixed> $items Items as returned by the panel.
     *
     * @throws InvalidArgumentException when the payload is not a list of toolbar items.
     */
    private static function assertToolbarItems(string $panelId, array $items): void
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException(
                Message::TOOLBAR_ITEMS_NOT_LIST->getMessage($panelId),
            );
        }

        foreach ($items as $item) {
            if (!$item instanceof ToolbarItem) {
                throw new InvalidArgumentException(
                    Message::TOOLBAR_ITEM_INVALID->getMessage($panelId),
                );
            }
        }
    }

    /**
     * Returns the target of a panel whose only metric links elsewhere, so its label and badge open the same capture.
     *
     * The toolbar renders the label and a linked badge as two separate links; a single metric pointing at another
     * capture (a cross-request Mail count) would otherwise leave the label on the current one. Metrics that sit beside
     * others (Logs' severity filters) keep the panel target.
     *
     * @param list<ToolbarItem> $items Toolbar metrics declared by the panel.
     *
     * @return string|null URL of the only metric, or `null` when there are several metrics or the only one has no URL.
     */
    private static function envelopeUrl(array $items): string|null
    {
        if (count($items) !== 1) {
            return null;
        }

        $url = $items[0]->url;

        return $url !== null && $url !== '' ? $url : null;
    }

    /**
     * Builds the single-metric panel shown when a capture or its toolbar contribution failed.
     *
     * @param string $id Stable panel identifier.
     * @param string $title Panel display name.
     * @param string $url Debug page URL.
     * @param string $message Failure diagnostic shown as the metric tooltip.
     * @param bool $extension `true` when the toolbar groups the panel under its Extensions menu; `false` when the
     * panel is built in and stays inline.
     *
     * @return ToolbarPanel Panel representing the failure.
     */
    private static function failedPanel(
        string $id,
        string $title,
        string $url,
        string $message,
        bool $extension,
    ): ToolbarPanel {
        return ToolbarPanel::create($id, $title)
            ->withUrl($url)
            ->withExtension($extension)
            ->withItems(
                [
                    ToolbarItem::create('error')
                        ->withLabel($title)
                        ->withStatus('danger')
                        ->withTitle($message),
                ],
            );
    }

    /**
     * Adds Logs panel filter URLs to its error and warning toolbar metrics.
     *
     * @param string $tag Tag of the capture the toolbar links to.
     * @param list<ToolbarItem> $items Toolbar metrics declared by the Logs panel.
     *
     * @return list<ToolbarItem> Metrics with the error and warning entries linked to a filtered grid.
     */
    private function logFilterLinks(string $tag, array $items): array
    {
        $linked = [];

        $urls = new DebugUrlGenerator($this->options->routePrefix);

        foreach ($items as $item) {
            $level = match ($item->id) {
                'errors' => LogLevel::ERROR,
                'warnings' => LogLevel::WARNING,
                default => null,
            };

            $linked[] = $level === null
                ? $item
                : $item->withUrl(
                    $urls->panel(
                        $tag,
                        'log',
                        [FilterPrefix::LOG => ['level' => (string) $level]],
                    ),
                );
        }

        return $linked;
    }

    /**
     * Builds the toolbar panel list for a capture, surfacing hydration failures as panel errors.
     *
     * @param string $tag Tag of the capture the toolbar links to.
     * @param DebugSnapshot $snapshot Capture the panels describe.
     *
     * @return list<ToolbarPanel> Panels in navigation order.
     */
    private function panels(string $tag, DebugSnapshot $snapshot): array
    {
        $toolbarPanels = [];

        foreach ($this->extensionPanels as $id => $panel) {
            $url = $this->viewUrl($tag, $id);
            $extension = !BuiltInPanels::isBuiltIn($id);

            $failure = $snapshot->failures[$id] ?? null;

            if ($failure !== null) {
                $toolbarPanels[] = self::failedPanel(
                    $id,
                    $panel->name(),
                    $url,
                    $failure->exception->getMessage(),
                    $extension,
                );

                continue;
            }

            if (!array_key_exists($id, $snapshot->panels)) {
                continue;
            }

            if (!$panel instanceof ToolbarPanelProviderInterface) {
                continue;
            }

            if (PanelContent::isPresent($panel, $snapshot->panels[$id]) === false) {
                continue;
            }

            try {
                $items = $panel instanceof CaptureToolbarProviderInterface
                    ? $panel->toolbarItemsForCapture($tag, $snapshot->panels[$id])
                    : $panel->toolbarItems($snapshot->panels[$id]);

                self::assertToolbarItems($id, $items);

                if ($panel instanceof LogPanel) {
                    $items = $this->logFilterLinks($tag, $items);
                }
            } catch (Throwable $throwable) {
                $toolbarPanels[] = self::failedPanel(
                    $id,
                    $panel->name(),
                    $url,
                    ExceptionSnapshot::fromThrowable($throwable)->getMessage(),
                    $extension,
                );

                continue;
            }

            if ($items === []) {
                continue;
            }

            $toolbarPanels[] = ToolbarPanel::create(
                $id,
                $panel instanceof ToolbarTitleProviderInterface ? $panel->toolbarTitle() : $panel->name(),
            )
                ->withUrl(self::envelopeUrl($items) ?? $url)
                ->withIcon($panel->icon())
                ->withExtension($extension)
                ->withItems($items);
        }

        return $toolbarPanels;
    }

    /**
     * Builds the URL opening one panel of a capture.
     *
     * @param string $tag Capture to open.
     * @param string $panelId Panel to open within that capture.
     *
     * @return string URL of the panel view.
     */
    private function viewUrl(string $tag, string $panelId): string
    {
        return "{$this->options->routePrefix}/view?tag=" . rawurlencode($tag) . '&panel=' . rawurlencode($panelId);
    }
}
