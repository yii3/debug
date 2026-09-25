<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use InvalidArgumentException;
use PHPForge\Debug\Helper\Trace;
use PHPForge\Debug\Storage\SnapshotStore;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Db\DbExplain;
use Yii3\Debug\Panel\{
    AssetPanel,
    BuiltInPanelList,
    BuiltInPanels,
    DbPanel,
    DumpPanel,
    EventPanel,
    ExtensionPanelInterface,
    LogPanel,
    MailPanel,
    ProfilingPanel,
    RequestPanel,
};
use Yii3\Debug\Web\DebugUrlGenerator;

use function array_map;
use function sys_get_temp_dir;

/**
 * Unit tests for {@see BuiltInPanelList} ordering the built-in panels from {@see BuiltInPanels::IDS} alone.
 */
final class BuiltInPanelListTest extends TestCase
{
    public function testFromMapOrdersThePanelsByTheIdClassification(): void
    {
        $panels = BuiltInPanelList::fromMap(
            [
                'asset' => new AssetPanel(),
                'db' => new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()),
                'dump' => new DumpPanel(Trace::create()),
                'mail' => self::mailPanel(),
                'profiling' => new ProfilingPanel(),
                'event' => new EventPanel(),
                'log' => new LogPanel(Trace::create()),
                'request' => new RequestPanel(),
            ],
        )->panels();

        self::assertSame(
            BuiltInPanels::IDS,
            array_map(static fn(ExtensionPanelInterface $panel): string => $panel->id(), $panels),
            'Order must come from the ID classification, not from the map.',
        );
    }

    public function testThrowInvalidArgumentExceptionWhenABuiltInPanelIsMissing(): void
    {
        $panels = self::panelsById();

        unset($panels['db']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Built-in debug panel "db" is missing.',
        );

        BuiltInPanelList::fromMap($panels);
    }

    public function testThrowInvalidArgumentExceptionWhenAPanelIsKeyedByAnotherId(): void
    {
        $panels = self::panelsById();

        $panels['log'] = new RequestPanel();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug panel registered as "log" must match its ID "request".',
        );

        BuiltInPanelList::fromMap($panels);
    }

    public function testThrowInvalidArgumentExceptionWhenAPanelIsKeyedByAnUnknownId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug panel "queue" is not built in. Built-in panels: request, log, event, profiling, db, mail, dump, asset.',
        );

        BuiltInPanelList::fromMap([...self::panelsById(), 'queue' => new RequestPanel()]);
    }

    private static function mailPanel(): MailPanel
    {
        return new MailPanel(new SnapshotStore(sys_get_temp_dir() . '/yii3-debug-built-in-list', 0o700, 0o600));
    }

    /**
     * @return array<string, ExtensionPanelInterface> Every built-in panel keyed by its ID.
     */
    private static function panelsById(): array
    {
        return [
            'request' => new RequestPanel(),
            'log' => new LogPanel(Trace::create()),
            'event' => new EventPanel(),
            'profiling' => new ProfilingPanel(),
            'db' => new DbPanel(new DbExplain(), new DebugUrlGenerator(), Trace::create()),
            'mail' => self::mailPanel(),
            'dump' => new DumpPanel(Trace::create()),
            'asset' => new AssetPanel(),
        ];
    }
}
