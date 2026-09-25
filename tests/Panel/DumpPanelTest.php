<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Helper\{LogLevel, Trace};
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\DumpPanel;
use Yii3\Debug\Tests\Provider\DumpPanelProvider;
use Yii3\Debug\Tests\Support\HelperFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

use function array_map;
use function dirname;
use function file_get_contents;
use function json_decode;
use function preg_match_all;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see DumpPanel} content detection, toolbar metric, and grid rendering, including a capture written
 * by the Yii2 host.
 *
 * {@see DumpPanelProvider} for test case data providers.
 */
final class DumpPanelTest extends TestCase
{
    private const string HEADING = "<h1 class=\"yii-debug-sr-only\">\nDump\n</h1>";

    public function testMetadataAndContentVisibility(): void
    {
        $panel = new DumpPanel(Trace::create());

        self::assertSame(
            'dump',
            $panel->id(),
            "Stable panel ID must be 'dump'.",
        );
        self::assertSame(
            'Dump',
            $panel->name(),
            "Panel name must be 'Dump'.",
        );
        self::assertSame(
            'dump',
            $panel->icon(),
            "Panel icon must be 'dump'.",
        );
        self::assertFalse(
            $panel->hasContent([]),
            'An absent capture must stay hidden.',
        );
        self::assertTrue(
            $panel->hasContent(['entries' => []]),
            'An idle capture must stay listed, as in Yii2.',
        );
    }

    public function testRenderEncodesTheCapturedCategory(): void
    {
        $html = self::render(
            DumpSnapshot::capture([['value', LogLevel::TRACE, '<b>app</b>', 1.0, []]])->jsonSerialize(),
            [],
        );

        self::assertStringContainsString(
            '>&lt;b&gt;app&lt;/b&gt;</td>',
            $html,
            'Category must be rendered as text.',
        );
    }

    public function testRenderExplainsWhenNoDumpsMatch(): void
    {
        $html = self::render(self::payload(), ['Log' => ['message' => 'missing']]);

        self::assertStringStartsWith(
            self::HEADING,
            $html,
            'Heading must precede the filtered-empty state.',
        );
        self::assertStringContainsString(
            'No dumps match the active filters',
            $html,
            'Filtered-empty result must be explained.',
        );
        self::assertStringContainsString(
            '>Clear all</a>',
            $html,
            'Filtered-empty result must offer a reset action.',
        );
        self::assertStringNotContainsString(
            'yii-debug-grid-dump',
            $html,
            'Filtered-empty result must not render an empty table.',
        );
    }

    public function testRenderMatchesTheYii2CellsForAYii2Capture(): void
    {
        $yii2Grid = (string) file_get_contents(dirname(__DIR__) . '/Support/Fixture/yii2-dump-grid.html');

        $html = self::render(self::yii2Capture()['panels']['dump'], []);

        self::assertStringStartsWith(
            self::HEADING . '<header class="yii-debug-grid-summary">',
            $html,
            'Heading and summary must open the panel, as in Yii2.',
        );
        self::assertSame(
            self::cells('~<div class="yii-debug-dump">.*?</div>\n</div>~s', $yii2Grid),
            self::cells('~<div class="yii-debug-dump">.*?</div>\n</div>~s', $html),
            'Dump cards must be byte-identical to the Yii2 grid.',
        );
        self::assertSame(
            self::cells('~<td class="yii-debug-cell-mono[^"]*">[^<]*</td>~', $yii2Grid),
            self::cells('~<td class="yii-debug-cell-mono[^"]*">[^<]*</td>~', $html),
            'Category cells must be byte-identical to the Yii2 grid.',
        );
        self::assertStringContainsString(
            '<span><strong>2</strong> dumps captured</span>',
            $html,
            'Summary must count the captured dumps like Yii2.',
        );
        self::assertStringContainsString(
            'name="Log[category]"',
            $html,
            'Grid must expose the Yii2 category filter.',
        );
        self::assertStringContainsString(
            'name="Log[message]"',
            $html,
            'Grid must expose the Yii2 message filter.',
        );
    }

    public function testRenderShowsTheDedicatedEmptyCaptureState(): void
    {
        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Dump
            </h1><div class="yii-debug-empty-state">
            <h2>
            No variables dumped in this request
            </h2><p>
            The Dump panel records the values dumped through yiisoft/var-dumper, so nothing was captured here. To populate this view, dump values anywhere in the request cycle:
            </p><pre class="yii-debug-empty-state-code">
            VarDumper::dump($value);
            d($user, $query);
            </pre>
            </div>
            HTML,
            self::render(['entries' => []], []),
            'Capture without dumps must render the complete guidance state.',
        );
    }

    /**
     * @param array<array-key, mixed> $query
     * @param list<string> $expectedMessages
     */
    #[DataProviderExternal(DumpPanelProvider::class, 'views')]
    public function testRenderSortsFiltersAndPaginatesTheDumps(array $query, array $expectedMessages): void
    {
        self::assertSame(
            $expectedMessages,
            self::cells('~<div class="yii-debug-dump-body">\s*(\w+)~', self::render(self::payload(), $query), 1),
            'Visible dumps must follow the requested view.',
        );
    }

    public function testToolbarItemsCountTheDumps(): void
    {
        $panel = new DumpPanel(Trace::create());

        self::assertSame(
            [['value' => '3', 'status' => 'info', 'title' => 'Number of dumped variables']],
            array_map(static fn(ToolbarItem $item): array => $item->jsonSerialize(), $panel->toolbarItems(self::payload())),
            'Metric must match the Yii2 Dump chip.',
        );
        self::assertSame(
            [],
            $panel->toolbarItems(['entries' => []]),
            'No dump means no metric.',
        );
    }

    /**
     * @return list<string>
     */
    private static function cells(string $pattern, string $html, int $group = 0): array
    {
        preg_match_all($pattern, $html, $matches);

        return $matches[$group] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(): array
    {
        return DumpSnapshot::capture(
            [
                ['alpha', LogLevel::TRACE, 'app.c2', 3.0, []],
                ['beta', LogLevel::TRACE, 'app.c3', 1.0, []],
                ['gamma', LogLevel::TRACE, 'app.c1', 2.0, []],
            ],
        )->jsonSerialize();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<array-key, mixed> $query
     */
    private static function render(array $payload, array $query): string
    {
        return (new DumpPanel(Trace::create()))->render(
            HelperFactory::createPanelRenderInput(
                $payload,
                new PanelRenderContext('request-1', 'dump', $query, 'light', new DebugUrlGenerator()),
            ),
        );
    }

    /**
     * @return array{summary: array<string, mixed>, panels: array{dump: array<string, mixed>}}
     */
    private static function yii2Capture(): array
    {
        /** @var array{summary: array<string, mixed>, panels: array{dump: array<string, mixed>}} */
        return json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/Support/Fixture/yii2-dump-capture.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
