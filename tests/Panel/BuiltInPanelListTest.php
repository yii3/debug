<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use InvalidArgumentException;
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
            'Debug panel "mail" is not built in. Built-in panels: request, log, event, profiling, db, asset.',
        );

        BuiltInPanelList::fromMap([...self::panelsById(), 'mail' => new RequestPanel()]);
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
            'asset' => new AssetPanel(),
        ];
    }
}
