<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

/**
 * Assembles the built-in panels in the single display order the sidebar and the toolbar both follow.
 *
 * The order mirrors {@see BuiltInPanels::IDS}, which classifies the same panels by ID.
 */
final readonly class BuiltInPanelList
{
    /**
     * @var list<ExtensionPanelInterface> Built-in panels in display order.
     */
    private array $panels;

    /**
     * @param RequestPanel $requestPanel Request and response panel.
     * @param LogPanel $logPanel Log messages panel.
     * @param EventPanel $eventPanel Dispatched events panel.
     * @param ProfilingPanel $profilingPanel Profiling timeline panel.
     * @param DbPanel $dbPanel Database queries panel.
     * @param AssetPanel $assetPanel Asset bundles panel.
     */
    public function __construct(
        RequestPanel $requestPanel,
        LogPanel $logPanel,
        EventPanel $eventPanel,
        ProfilingPanel $profilingPanel,
        DbPanel $dbPanel,
        AssetPanel $assetPanel,
    ) {
        $this->panels = [$requestPanel, $logPanel, $eventPanel, $profilingPanel, $dbPanel, $assetPanel];
    }

    /**
     * Returns the built-in panels every host navigation starts from.
     *
     * @return list<ExtensionPanelInterface> Built-in panels in display order.
     */
    public function panels(): array
    {
        return $this->panels;
    }
}
