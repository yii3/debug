<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Helper\Trace;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Panel\{
    AssetPanel,
    BuiltInPanelList,
    BuiltInPanels,
    DbPanel,
    EventPanel,
    ExtensionPanelInterface,
    LogPanel,
    ProfilingPanel,
    RequestPanel,
};
use Yii3\Debug\Web\DebugUrlGenerator;

use function array_map;

/**
 * Unit tests for {@see BuiltInPanelList} assembling the single built-in display order every host navigation follows.
 */
final class BuiltInPanelListTest extends TestCase
{
    public function testPanelsFollowTheIdClassificationOrder(): void
    {
        $panels = (new BuiltInPanelList(
            new RequestPanel(),
            new LogPanel(Trace::create()),
            new EventPanel(),
            new ProfilingPanel(),
            new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()),
            new AssetPanel(),
        ))->panels();

        self::assertSame(
            BuiltInPanels::IDS,
            array_map(static fn(ExtensionPanelInterface $panel): string => $panel->id(), $panels),
            'Order must match the ID classification.',
        );
    }
}
