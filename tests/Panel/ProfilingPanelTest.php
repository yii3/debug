<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\ProfilingPanel;
use Yii3\Debug\Web\DebugUrlGenerator;

use function array_slice;
use function strpos;
use function substr;
use function substr_count;

/**
 * Unit tests for the filterable Profiling panel and its toolbar metrics.
 */
final class ProfilingPanelTest extends TestCase
{
    public function testContextFreeRenderShowsCapturedRowsWithoutControls(): void
    {
        $html = (new ProfilingPanel())
            ->render(self::payload());

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span>
            </header><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            Time
            </th><th scope="col">
            Duration
            </th><th scope="col">
            Category
            </th><th scope="col">
            Info
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.000">00:00:01.000</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">100.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span>
            </td><td>
            SLOW application
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.025">00:00:01.025</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 10%;'><span class="yii-debug-gauge-value">10.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span>
            </td><td>
            <span class="yii-debug-indent">→</span><div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.050">00:00:01.050</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">50.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\View::render"><span class="yii-debug-muted">Yii3\</span><wbr><strong>View::render</strong></span>
            </td><td>
            MIDDLE view
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-3 of 3 items.</span>
            </div>
            </div>
            HTML,
            $html,
            'Context-free rendering must match the complete profiling grid without query controls.',
        );
    }

    public function testContextWithoutSummaryKeepsLegacyTableOnlyRendering(): void
    {
        $html = (new ProfilingPanel())
            ->renderWithContext(self::payload(), self::context([]));

        self::assertStringNotContainsString(
            'class="yii-debug-tabs"',
            $html,
            'The existing context-only contract must retain its Table-only output.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-profile',
            $html,
            'Legacy rendering must keep the complete profiling table.',
        );
    }

    public function testLegacyViewParameterIsIgnoredAndNeverEmitted(): void
    {
        $panel = new ProfilingPanel();

        $summary = RequestSummary::create('request-1')
            ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
            ->withProfiling(0.1, 2_097_152);

        $unified = $panel->renderWithContextAndSummary(
            self::payload(),
            self::context([]),
            $summary,
        );
        $legacyUrl = $panel->renderWithContextAndSummary(
            self::payload(),
            self::context(['Timeline' => ['category' => 'ignored'], 'view' => 'timeline']),
            $summary,
        );

        self::assertSame(
            $unified,
            $legacyUrl,
            'Legacy view and Timeline parameters must not change the unified representation.',
        );
        self::assertStringContainsString(
            '<section class="yii-debug-tl">',
            $unified,
            'The unified page must always render its Timeline overview.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-profile',
            $unified,
            'The unified page must always render its detailed table.',
        );
        self::assertStringNotContainsString(
            'view=timeline',
            $legacyUrl,
            'Generated links must not emit the removed view state.',
        );
    }

    public function testMalformedPayloadRetainsTheNativeHydrationFailure(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot',
        );

        (new ProfilingPanel())->render(['memory' => '2 MB']);
    }

    public function testMetadataVisibilityAndContractsIdentifyTheBuiltInPanel(): void
    {
        $panel = new ProfilingPanel();

        self::assertSame(
            'profiling',
            $panel->id(),
            'Stable ID must pair with the profiling collector.',
        );
        self::assertSame(
            'profiling',
            $panel->icon(),
            'Icon must use the shared profiling glyph.',
        );
        self::assertSame(
            'Profiling',
            $panel->name(),
            'Sidebar label must match the Yii2 panel.',
        );
        self::assertSame(
            '',
            $panel->toolbarTitle(),
            'The toolbar must show only the icon and metric chips.',
        );
        self::assertTrue(
            $panel->hasContent(self::emptyPayload()),
            'A valid capture must expose the panel even when it contains no explicit spans.',
        );
    }

    public function testRenderEscapesCapturedInformation(): void
    {
        $html = (new ProfilingPanel())
            ->render(self::payload('<script>alert(1)</script>'));

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span>
            </header><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            Time
            </th><th scope="col">
            Duration
            </th><th scope="col">
            Category
            </th><th scope="col">
            Info
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.000">00:00:01.000</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">100.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span>
            </td><td>
            &lt;script&gt;alert(1)&lt;/script&gt;
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.025">00:00:01.025</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 10%;'><span class="yii-debug-gauge-value">10.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span>
            </td><td>
            <span class="yii-debug-indent">→</span><div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.050">00:00:01.050</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">50.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\View::render"><span class="yii-debug-muted">Yii3\</span><wbr><strong>View::render</strong></span>
            </td><td>
            MIDDLE view
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-3 of 3 items.</span>
            </div>
            </div>
            HTML,
            $html,
            'Captured profiler information must match the complete safely encoded grid.',
        );
    }

    public function testRenderShowsSummaryStripAndGuidanceWithoutProfilingData(): void
    {
        $html = (new ProfilingPanel())
            ->render(self::emptyPayload());

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>0</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>13 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span>
            </header><div class="yii-debug-empty-state">
            <h2>
            No profiling data captured
            </h2><p>
            This request did not produce any <code>ProfilerInterface::begin()</code> / <code>ProfilerInterface::end()</code> spans, so the Timeline and details are empty.
            </p><p>
            To populate this view, wrap interesting sections of code with profile markers:
            </p><pre class="yii-debug-empty-state-code">
            $profiler-&gt;begin('my-token');
            // …work…
            $profiler-&gt;end('my-token');
            </pre><p>
            Database queries are profiled automatically when the DB collector is configured.
            </p>
            </div>
            HTML,
            $html,
            'An empty profiling capture must match the complete summary and guidance state.',
        );
    }

    public function testRenderWithContextExplainsWhenFiltersMatchNoSpans(): void
    {
        $html = (new ProfilingPanel())
            ->renderWithContext(
                self::payload(),
                self::context(['Profile' => ['info' => 'missing'], 'per-page' => '25']),
            );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>0</strong> of 3 spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span>
            </header><div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=25" title="Remove this filter" aria-label="Remove info: missing filter"><span class="yii-debug-active-filter-attr">info</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">missing</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=25" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-empty-state">
            <h2>
            No spans match the active filters
            </h2><p>
            Adjust or clear the filters to show the captured spans.
            </p>
            </div>
            HTML,
            $html,
            'A filtered-empty result must match the complete active-filter diagnostic state.',
        );
    }

    public function testRenderWithContextPaginatesAndPreservesQueryStateInLinks(): void
    {
        $html = (new ProfilingPanel())
            ->renderWithContext(
                self::payload(),
                self::context(
                    [
                        'Profile' => ['category' => 'i'],
                        'sort' => '-duration',
                        'per-page' => '1',
                        'page' => '2',
                    ],
                ),
            );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all">
            All
            </option>
            </select></label>
            </header><div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=-duration&amp;per-page=1" title="Remove this filter" aria-label="Remove category: i filter"><span class="yii-debug-active-filter-attr">category</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">i</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=-duration&amp;per-page=1" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=seq&amp;per-page=1">Time</a>
            </th><th scope="col">
            <a class="desc" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=duration&amp;per-page=1">Duration</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=category&amp;per-page=1">Category</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=info&amp;per-page=1">Info</a>
            </th>
            </tr><tr class="filters">
            <td>
            </td><td>
            </td><td>
            <input class="yii-debug-input" name="Profile[category]" type="text" value="i">
            </td><td>
            <input class="yii-debug-input" name="Profile[info]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.050">00:00:01.050</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">50.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\View::render"><span class="yii-debug-muted">Yii3\</span><wbr><strong>View::render</strong></span>
            </td><td>
            MIDDLE view
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 2-2 of 3 items.</span><ul class="yii-debug-pager">
            <li class="yii-debug-pager-item">
            <a class="yii-debug-pager-link" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=-duration&amp;per-page=1&amp;page=1">1</a>
            </li><li class="yii-debug-pager-item is-active">
            <a class="yii-debug-pager-link" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=-duration&amp;per-page=1&amp;page=2">2</a>
            </li><li class="yii-debug-pager-item">
            <a class="yii-debug-pager-link" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=i&amp;sort=-duration&amp;per-page=1&amp;page=3">3</a>
            </li>
            </ul>
            </div>
            </div>
            HTML,
            $html,
            'Pagination must match the complete grid while preserving filter, sort, and page-size state.',
        );
    }

    public function testRenderWithContextProvidesTheCompleteFilteredGridContract(): void
    {
        $html = (new ProfilingPanel())
            ->renderWithContext(
                self::payload(),
                self::context(
                    [
                        'Profile' => ['category' => 'db\\command', 'info' => 'select'],
                        'per-page' => '25',
                        'page' => '2',
                    ],
                ),
            );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>1</strong> of 3 spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25" selected>
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all">
            All
            </option>
            </select></label>
            </header><div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">2 filters active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Binfo%5D=select&amp;per-page=25" title="Remove this filter" aria-label="Remove category: db\command filter"><span class="yii-debug-active-filter-attr">category</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">db\command</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=db%5Ccommand&amp;per-page=25" title="Remove this filter" aria-label="Remove info: select filter"><span class="yii-debug-active-filter-attr">info</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">select</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=25" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=db%5Ccommand&amp;Profile%5Binfo%5D=select&amp;per-page=25&amp;sort=seq">Time</a>
            </th><th scope="col">
            <a class="desc" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=db%5Ccommand&amp;Profile%5Binfo%5D=select&amp;per-page=25&amp;sort=duration">Duration</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=db%5Ccommand&amp;Profile%5Binfo%5D=select&amp;per-page=25&amp;sort=category">Category</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=db%5Ccommand&amp;Profile%5Binfo%5D=select&amp;per-page=25&amp;sort=info">Info</a>
            </th>
            </tr><tr class="filters">
            <td>
            </td><td>
            </td><td>
            <input class="yii-debug-input" name="Profile[category]" type="text" value="db\command">
            </td><td>
            <input class="yii-debug-input" name="Profile[info]" type="text" value="select">
            </td>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.025">00:00:01.025</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 10%;'><span class="yii-debug-gauge-value">10.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span>
            </td><td>
            <span class="yii-debug-indent">→</span><div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-1 of 1 items.</span>
            </div>
            </div>
            HTML,
            $html,
            'Filtering must match the complete profiling grid, controls, and removable filter state.',
        );
    }

    public function testRenderWithContextSortsRowsAndMarksTheActiveDirection(): void
    {
        $ascending = (new ProfilingPanel())
            ->renderWithContext(
                self::payload(),
                self::context(['sort' => 'duration', 'per-page' => 'all']),
            );
        $descending = (new ProfilingPanel())
            ->renderWithContext(
                self::payload(),
                self::context(['sort' => '-duration', 'per-page' => 'all']),
            );
        $default = (new ProfilingPanel())
            ->renderWithContext(
                self::payload(),
                self::context(['per-page' => 'all']),
            );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all" selected>
            All
            </option>
            </select></label>
            </header><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=seq&amp;per-page=all">Time</a>
            </th><th scope="col">
            <a class="asc" href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=-duration&amp;per-page=all">Duration</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=category&amp;per-page=all">Category</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=info&amp;per-page=all">Info</a>
            </th>
            </tr><tr class="filters">
            <td>
            </td><td>
            </td><td>
            <input class="yii-debug-input" name="Profile[category]" type="text">
            </td><td>
            <input class="yii-debug-input" name="Profile[info]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.025">00:00:01.025</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 10%;'><span class="yii-debug-gauge-value">10.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span>
            </td><td>
            <span class="yii-debug-indent">→</span><div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.050">00:00:01.050</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">50.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\View::render"><span class="yii-debug-muted">Yii3\</span><wbr><strong>View::render</strong></span>
            </td><td>
            MIDDLE view
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.000">00:00:01.000</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">100.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span>
            </td><td>
            SLOW application
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-3 of 3 items.</span>
            </div>
            </div>
            HTML,
            $ascending,
            'Ascending duration sorting must match the complete ordered grid and active direction.',
        );
        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all" selected>
            All
            </option>
            </select></label>
            </header><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=seq&amp;per-page=all">Time</a>
            </th><th scope="col">
            <a class="desc" href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=duration&amp;per-page=all">Duration</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=category&amp;per-page=all">Category</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;sort=info&amp;per-page=all">Info</a>
            </th>
            </tr><tr class="filters">
            <td>
            </td><td>
            </td><td>
            <input class="yii-debug-input" name="Profile[category]" type="text">
            </td><td>
            <input class="yii-debug-input" name="Profile[info]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.000">00:00:01.000</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">100.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span>
            </td><td>
            SLOW application
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.050">00:00:01.050</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">50.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\View::render"><span class="yii-debug-muted">Yii3\</span><wbr><strong>View::render</strong></span>
            </td><td>
            MIDDLE view
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.025">00:00:01.025</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 10%;'><span class="yii-debug-gauge-value">10.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span>
            </td><td>
            <span class="yii-debug-indent">→</span><div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-3 of 3 items.</span>
            </div>
            </div>
            HTML,
            $descending,
            'Descending duration sorting must match the complete ordered grid and active direction.',
        );
        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>3</strong> spans</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50">
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all" selected>
            All
            </option>
            </select></label>
            </header><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=all&amp;sort=seq">Time</a>
            </th><th scope="col">
            <a class="desc" href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=all&amp;sort=duration">Duration</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=all&amp;sort=category">Category</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;per-page=all&amp;sort=info">Info</a>
            </th>
            </tr><tr class="filters">
            <td>
            </td><td>
            </td><td>
            <input class="yii-debug-input" name="Profile[category]" type="text">
            </td><td>
            <input class="yii-debug-input" name="Profile[info]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.000">00:00:01.000</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">100.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span>
            </td><td>
            SLOW application
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.050">00:00:01.050</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">50.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\View::render"><span class="yii-debug-muted">Yii3\</span><wbr><strong>View::render</strong></span>
            </td><td>
            MIDDLE view
            </td>
            </tr><tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.025">00:00:01.025</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 10%;'><span class="yii-debug-gauge-value">10.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span>
            </td><td>
            <span class="yii-debug-indent">→</span><div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-3 of 3 items.</span>
            </div>
            </div>
            HTML,
            $default,
            'Default sorting must match the complete descending-duration grid and links.',
        );
    }

    public function testSummaryContextRendersTimelineAndTableFromSharedFilteredRows(): void
    {
        $payload = self::payload();

        $payload['samples'] = [
            ['time' => 1_000.0, 'memory' => 1_048_576],
            ['time' => 1_100.0, 'memory' => 2_097_152],
        ];

        $html = (new ProfilingPanel())
            ->renderWithContextAndSummary(
                $payload,
                self::context(
                    [
                        'Profile' => ['duration' => '20', 'category' => 'Yii3', 'info' => 'i'],
                        'page' => '2',
                        'per-page' => '1',
                        'sort' => '-duration',
                        'yii_debug_theme' => 'dark',
                    ],
                    [
                        'log' => LogSnapshot::capture(
                            [['checkpoint', 200, 'application', 1.075, [], 1_572_864]],
                        )->jsonSerialize(),
                    ],
                ),
                RequestSummary::create('request-1')
                    ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
                    ->withProfiling(0.1, 2_097_152),
            );

        $tableOffset = strpos($html, '<div class="yii-debug-grid yii-debug-grid-profile">');
        $timelineOffset = strpos($html, '<section class="yii-debug-tl">');
        $detailsOffset = strpos($html, '<header class="yii-debug-section-header">');

        self::assertIsInt(
            $tableOffset,
            'The unified page must contain the detailed profile grid.',
        );
        self::assertIsInt(
            $timelineOffset,
            'The unified page must contain the Timeline overview.',
        );
        self::assertIsInt(
            $detailsOffset,
            'The unified page must contain the details heading.',
        );

        $tableHtml = substr($html, $tableOffset);
        $timelineHtml = substr($html, $timelineOffset, $detailsOffset - $timelineOffset);

        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-grid-summary"'),
            'The unified page must render one shared capture summary.',
        );
        self::assertStringNotContainsString(
            'class="yii-debug-tabs"',
            $html,
            'The unified page must not retain the redundant representation tabs.',
        );
        self::assertStringNotContainsString(
            'aria-label="Profiling views"',
            $html,
            'Timeline and table must be document sections rather than separate destinations.',
        );
        self::assertStringContainsString(
            'aria-label="Profiling filters"',
            $html,
            'The shared filters must be exposed as one labelled form.',
        );
        self::assertStringContainsString(
            '<strong>2</strong> of 3 spans',
            $html,
            'The summary must distinguish the shared filtered subset from the capture total.',
        );
        self::assertStringContainsString(
            '<h2>' . "\n" . 'Timeline',
            $html,
            'The chart must be a visible section of the unified page.',
        );
        self::assertStringContainsString(
            '<h2>' . "\n" . 'Details',
            $html,
            'The detailed grid must be a visible section of the unified page.',
        );
        self::assertLessThan(
            strpos($html, 'yii-debug-grid-profile'),
            strpos($html, '<section class="yii-debug-tl">'),
            'The Timeline overview must precede the detailed table.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-row-app',
            $html,
            'Application spans must use the shared Timeline category styling.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-row-view',
            $html,
            'View spans must use the shared Timeline category styling.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-memory-gradient',
            $html,
            'Profiler memory samples must feed the shared memory graph.',
        );
        self::assertStringContainsString(
            'name="Profile[duration]" type="number" value="20" min="0"',
            $html,
            'Minimum duration must use the shared Profile filter group.',
        );
        self::assertStringContainsString(
            'name="Profile[category]" type="text" value="Yii3"',
            $html,
            'Category must use the shared Profile filter group.',
        );
        self::assertStringContainsString(
            'name="Profile[info]" type="text" value="i"',
            $html,
            'Info must use the shared Profile filter group.',
        );
        self::assertStringContainsString(
            'value="request-1"',
            $html,
            'The shared filter form must preserve the selected capture tag.',
        );
        self::assertStringContainsString(
            'value="dark"',
            $html,
            'The shared filter form must preserve an explicit theme selection.',
        );
        self::assertStringNotContainsString(
            'name="view"',
            $html,
            'The shared form must not preserve the removed representation parameter.',
        );
        self::assertStringNotContainsString(
            'name="Timeline[',
            $html,
            'The obsolete Timeline filter namespace must not be emitted.',
        );
        self::assertStringNotContainsString(
            'name="page"',
            $html,
            'Applying shared filters must reset table pagination.',
        );
        self::assertStringContainsString(
            'name="sort" type="hidden" value="-duration"',
            $html,
            'Applying a shared filter must preserve the table sort state.',
        );
        self::assertStringContainsString(
            'name="per-page" type="hidden" value="1"',
            $html,
            'Applying shared filters must preserve the table page size.',
        );
        self::assertSame(
            2,
            substr_count($html, 'class="yii-debug-tl-row yii-debug-tl-row-'),
            'The Timeline must retain every shared filtered row before table pagination.',
        );
        self::assertStringNotContainsString(
            '<wbr>',
            $timelineHtml,
            'Timeline labels must not expose a line-break opportunity.',
        );
        self::assertStringContainsString(
            '<wbr>',
            $tableHtml,
            'Removing Timeline breaks must not change the detailed table labels.',
        );
        self::assertStringNotContainsString(
            'class="yii-debug-cell-numeric"',
            $tableHtml,
            'Duration gauges must retain Yii2-compatible left alignment.',
        );
        self::assertStringNotContainsString(
            'SLOW application',
            $tableHtml,
            'The first filtered span must remain in the Timeline while page two omits it from the table.',
        );
        self::assertStringContainsString(
            'MIDDLE view',
            $tableHtml,
            'The second filtered span must appear in the requested table page.',
        );
        self::assertStringNotContainsString(
            'SELECT 1',
            $tableHtml,
            'The same shared filters must exclude the database span from both representations.',
        );
        self::assertStringContainsString(
            "style='left: 0%; width: 100%;'",
            $html,
            'The request-start span must fill the exact 100 ms capture geometry.',
        );
        self::assertStringContainsString(
            "style='left: 50%; width: 50%;'",
            $html,
            'A span beginning 50 ms into the request must occupy its exact half-width geometry.',
        );
        self::assertStringContainsString(
            'points="0 40 0 20 1440 10 1920 0 1920 0"',
            $html,
            'Memory geometry must combine sorted profiler and log samples at exact millisecond coordinates.',
        );
    }

    public function testSummaryUsesTheSingularSpanLabel(): void
    {
        $html = (new ProfilingPanel())
            ->render(self::payload(entryCount: 1));

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>1</strong> span</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>100 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span>
            </header><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            Time
            </th><th scope="col">
            Duration
            </th><th scope="col">
            Category
            </th><th scope="col">
            Info
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="1970-01-01 00:00:01.000">00:00:01.000</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">100.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span>
            </td><td>
            SLOW application
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-1 of 1 items.</span>
            </div>
            </div>
            HTML,
            $html,
            'A single profile row must match the complete grid with the singular span label.',
        );
    }

    public function testTimelineIgnoresMalformedSiblingLogPayload(): void
    {
        $payload = self::payload();

        $payload['samples'] = [
            ['time' => 1_000.0, 'memory' => 1_048_576],
            ['time' => 1_100.0, 'memory' => 2_097_152],
        ];
        $html = (new ProfilingPanel())
            ->renderWithContextAndSummary(
                $payload,
                self::context([], ['log' => ['entries' => [['invalid']]]]),
                RequestSummary::create('request-1')
                    ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
                    ->withProfiling(0.1, 2_097_152),
            );

        self::assertStringContainsString(
            '<section class="yii-debug-tl">',
            $html,
            'Malformed optional Log data must not prevent valid profiling data from rendering.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-profile',
            $html,
            'Malformed optional Log data must not prevent the profiling details from rendering.',
        );
        self::assertStringContainsString(
            'yii-debug-tl-memory-gradient',
            $html,
            'Malformed optional Log data must not discard valid profiler memory samples.',
        );
    }

    public function testTimelineReportsUnavailableGeometryWithoutInventingFallbackTiming(): void
    {
        $validSummary = RequestSummary::create('request-1')
            ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
            ->withProfiling(0.1, 2_097_152);
        $missingStart = RequestSummary::create('request-1')
            ->withProfiling(0.1, 2_097_152);

        $missingDuration = self::payload();

        $missingDuration['time'] = 0.0;

        $missingMemory = self::payload();

        $missingMemory['memory'] = 0;

        $panel = new ProfilingPanel();

        foreach (
            [
                'request start' => [self::payload(), $missingStart],
                'profiling duration' => [$missingDuration, $validSummary],
                'peak memory' => [$missingMemory, $validSummary],
            ] as $missing => [$payload, $summary]
        ) {
            $html = $panel->renderWithContextAndSummary(
                $payload,
                self::context([]),
                $summary,
            );

            self::assertStringContainsString(
                'Timeline unavailable',
                $html,
                "Missing {$missing} must produce a dedicated unavailable state.",
            );
            self::assertStringContainsString(
                'yii-debug-tl-filter',
                $html,
                "Missing {$missing} must retain shared filters for the detailed table.",
            );
            self::assertStringNotContainsString(
                '<section class="yii-debug-tl">',
                $html,
                "Missing {$missing} must not fabricate a request-relative chart.",
            );
            self::assertStringContainsString(
                'yii-debug-grid-profile',
                $html,
                "Missing {$missing} must keep the detailed profile table available.",
            );
        }
    }

    public function testTimelineShowsShortClassNameAndKeepsFullCategoryOnHover(): void
    {
        $payload = self::payload(
            entryCount: 1,
            firstCategory: 'App\\Web\\Workbench\\HomeAction::__invoke',
        );

        $html = (new ProfilingPanel())
            ->renderWithContextAndSummary(
                $payload,
                self::context([]),
                RequestSummary::create('request-1')
                    ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
                    ->withProfiling(0.1, 2_097_152),
            );

        self::assertStringContainsString(
            '<span class="yii-debug-tl-name" title="App\\Web\\Workbench\\HomeAction::__invoke">'
            . '<strong>HomeAction</strong></span>',
            $html,
            'Timeline must keep only the short class visible and expose the full category through its hover title.',
        );
    }

    public function testTimelineUsesExactRequestRelativeMillisecondGeometry(): void
    {
        $payload = self::payload();

        $payload['samples'] = [
            ['time' => 1_000.0, 'memory' => 1_048_576],
            ['time' => 1_100.0, 'memory' => 2_097_152],
        ];

        $html = (new ProfilingPanel())
            ->renderWithContextAndSummary(
                $payload,
                self::context([]),
                RequestSummary::create('request-1')
                    ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
                    ->withProfiling(0.1, 2_097_152),
            );

        self::assertStringContainsString(
            '<strong>100 ms</strong> total',
            $html,
            'The profiling duration must cross the seconds-to-milliseconds boundary exactly once.',
        );
        self::assertStringContainsString(
            "style='left: 0%; width: 100%;'",
            $html,
            'The outer 100 ms span must occupy the complete 100 ms request.',
        );
        self::assertStringContainsString(
            "style='left: 25%; width: 10%;'",
            $html,
            'The 10 ms database span must begin 25 ms into the request.',
        );
        self::assertStringContainsString(
            "style='left: 50%; width: 50%;'",
            $html,
            'The 50 ms view span must occupy the second half of the request.',
        );
        self::assertStringContainsString(
            'points="0 40 0 20 1920 0 1920 0"',
            $html,
            'Memory samples at the request boundaries must map to x=0 and x=1920.',
        );
    }

    public function testToolbarItemsExposeTimeAndMemoryChips(): void
    {
        $payload = self::emptyPayload();

        $payload['time'] = 1.2345;

        $items = (new ProfilingPanel())
            ->toolbarItems($payload);

        self::assertCount(
            2,
            $items,
            'Both metrics must be emitted.',
        );
        self::assertSame(
            '1,235 ms',
            $items[0]->value,
            'Time chip must use thousands separators.',
        );
        self::assertSame(
            'Total processing time',
            $items[0]->title,
            'Time chip must carry its tooltip.',
        );
        self::assertSame(
            '2.000 MB',
            $items[1]->value,
            'Memory chip must render megabytes.',
        );
        self::assertSame(
            'Peak memory',
            $items[1]->title,
            'Memory chip must carry its tooltip.',
        );
    }

    public function testUnifiedScreenDistinguishesAnEmptyCaptureFromAnEmptyFilterResult(): void
    {
        $summary = RequestSummary::create('request-1')
            ->withRequest('https://example.test/profile', 'GET', '127.0.0.1', 1.0)
            ->withProfiling(0.1, 2_097_152);

        $panel = new ProfilingPanel();

        $captureEmpty = $panel->renderWithContextAndSummary(
            self::emptyPayload(),
            self::context([]),
            $summary,
        );
        $filterEmpty = $panel->renderWithContextAndSummary(
            self::payload(),
            self::context(
                [
                    'Profile' => ['category' => 'missing'],
                ],
            ),
            $summary,
        );

        self::assertStringContainsString(
            'No profiling data captured',
            $captureEmpty,
            'A request without profiler spans must describe the missing capture rather than blame a filter.',
        );
        self::assertStringNotContainsString(
            'yii-debug-tl-filter',
            $captureEmpty,
            'A capture-empty request must not expose filters for data that was never captured.',
        );
        self::assertStringContainsString(
            'No spans match the active filters',
            $filterEmpty,
            'A nonempty capture filtered to zero must identify the shared active filters.',
        );
        self::assertStringContainsString(
            '>Clear all</a>',
            $filterEmpty,
            'A filtered-empty page must provide one direct shared reset action.',
        );
        self::assertStringNotContainsString(
            '<section class="yii-debug-tl">',
            $filterEmpty,
            'A shared empty result must not render an empty chart.',
        );
        self::assertStringNotContainsString(
            'yii-debug-grid-profile',
            $filterEmpty,
            'A shared empty result must not render a second table empty state.',
        );
    }

    /**
     * @param array<array-key, mixed> $queryParams
     * @param array<string, array<string, mixed>> $panels
     */
    private static function context(array $queryParams, array $panels = []): PanelRenderContext
    {
        return new PanelRenderContext(
            'request-1',
            'profiling',
            $queryParams,
            'light',
            new DebugUrlGenerator(),
            $panels,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPayload(): array
    {
        return [
            'memory' => 2_097_152,
            'time' => 0.0125,
            'entries' => [],
            'samples' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(
        string $firstInfo = 'SLOW application',
        int $entryCount = 3,
        string $firstCategory = 'Yii3\\Application::handle',
    ): array {
        $entries = [
            [
                'timestamp' => 1_000.0,
                'duration' => 100.0,
                'category' => $firstCategory,
                'info' => $firstInfo,
                'level' => 0,
                'seq' => 0,
                'memory' => 1_048_576,
                'memoryDiff' => 0,
                'trace' => [],
            ],
            [
                'timestamp' => 1_025.0,
                'duration' => 10.0,
                'category' => 'Yiisoft\\Db\\Command::query',
                'info' => 'SELECT 1',
                'level' => 1,
                'seq' => 1,
                'memory' => 1_572_864,
                'memoryDiff' => 524_288,
                'trace' => [],
            ],
            [
                'timestamp' => 1_050.0,
                'duration' => 50.0,
                'category' => 'Yii3\\View::render',
                'info' => 'MIDDLE view',
                'level' => 0,
                'seq' => 2,
                'memory' => 1_835_008,
                'memoryDiff' => 262_144,
                'trace' => [],
            ],
        ];

        return [
            'memory' => 2_097_152,
            'time' => 0.1,
            'entries' => array_slice($entries, 0, $entryCount),
            'samples' => [],
        ];
    }
}
