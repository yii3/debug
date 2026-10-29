<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPForge\Debug\View\ViewMessage;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Comparison\HistoryComparison;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yii3\Debug\Tests\Provider\HistoryComparisonRendererProvider;
use Yii3\Debug\Web\{DebugPageRenderer, HistoryComparisonRenderer, HistoryGridRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function preg_quote;
use function str_repeat;
use function substr;
use function sys_get_temp_dir;

/**
 * Integration tests for the capture-comparison form and results presentation.
 */
final class HistoryComparisonRendererTest extends TestCase
{
    public function testDedicatedPageRendersTargetSidebarAndComparisonResults(): void
    {
        [$comparison, $manifest] = $this->comparison();

        $html = $this->pageRenderer()
            ->compare($comparison, $manifest, 'dark');

        self::assertStringContainsString(
            '<title>Compare captures — Yii Debugger</title>',
            $html,
            'Document title must identify the capture comparison.',
        );
        self::assertStringContainsString(
            'data-yii-debug-theme="dark"',
            $html,
            'Comparison page must retain the resolved debugger theme.',
        );
        self::assertMatchesRegularExpression(
            '/Current request.*data-snapshot-field="method">POST<.*data-snapshot-field="url"[^>]*>\/target/s',
            $html,
            'Sidebar must represent the selected target capture rather than the baseline.',
        );
        self::assertStringContainsString(
            'yii-debug-delta-up">+5.00 ms (+50.0%)',
            $html,
            'Request metrics must present the delta from baseline to target.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-section-count">2</span>',
            $html,
            'Panel structure heading must expose the compared panel count.',
        );

        $captions = [
            ViewMessage::COMPARISON_METRICS_CAPTION,
            ViewMessage::COMPARISON_PANELS_CAPTION,
        ];

        foreach ($captions as $caption) {
            self::assertMatchesRegularExpression(
                '~<div\b(?=[^>]*\bclass="yii-debug-table-wrap")(?=[^>]*\brole="region")(?=[^>]*\btabindex="0")'
                . '(?=[^>]*\baria-label="' . preg_quote($caption->value, '~') . '")[^>]*>~',
                $html,
                "Region `{$caption->value}` must be focusable and named by its own caption.",
            );
        }
    }

    public function testHistoryFormSelectsTheTwoNewestCapturesWhenMoreAreRetained(): void
    {
        self::assertSame(
            <<<HTML
            <section class="yii-debug-section" aria-labelledby="yii-debug-history-compare-title">
            <h2 class="yii-debug-section-title" id="yii-debug-history-compare-title">
            <span class="yii-debug-section-mark">Compare</span>Capture changes
            </h2><form class="yii-debug-compare-form" action="/developer/debug/compare" method="get">
            <div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-baseline">Baseline capture</label><select class="yii-debug-select" id="yii-debug-compare-baseline" name="baseline" required>
            <option value="newest">
            time unavailable · UNKNOWN ·  · newest
            </option>
            <option value="middle" selected>
            time unavailable · UNKNOWN ·  · middle
            </option>
            <option value="oldest">
            time unavailable · UNKNOWN ·  · oldest
            </option>
            </select>
            </div><div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-target">Target capture</label><select class="yii-debug-select" id="yii-debug-compare-target" name="target" required>
            <option value="newest" selected>
            time unavailable · UNKNOWN ·  · newest
            </option>
            <option value="middle">
            time unavailable · UNKNOWN ·  · middle
            </option>
            <option value="oldest">
            time unavailable · UNKNOWN ·  · oldest
            </option>
            </select>
            </div><div class="yii-debug-field">
            <button class="yii-debug-btn yii-debug-btn-primary" type="submit">Compare captures</button>
            </div>
            </form>
            </section>
            HTML,
            HistoryComparisonRenderer::renderHistoryForm(
                [
                    'newest' => RequestSummary::create('newest'),
                    'middle' => RequestSummary::create('middle'),
                    'oldest' => RequestSummary::create('oldest'),
                ],
                '/developer/debug',
            ),
            'Baseline must be the second-newest capture and target the newest one.',
        );
    }

    public function testHistoryGridOffersComparisonOnlyWhenTwoCapturesExist(): void
    {
        [$comparison, $manifest] = $this->comparison();

        $pair = HistoryGridRenderer::render(
            $manifest,
            [],
            '/developer/debug',
        );
        $single = HistoryGridRenderer::render(
            ['target-capture' => $comparison->target->summary],
            [],
            '/developer/debug',
        );

        self::assertStringContainsString(
            'id="yii-debug-history-compare-title"',
            $pair,
            'Two retained captures must expose the comparison selection section.',
        );
        self::assertStringContainsString(
            'action="/developer/debug/compare" method="get"',
            $pair,
            'Comparison selection must submit a read-only request to the configured prefix.',
        );
        self::assertMatchesRegularExpression(
            '/name="baseline".*value="baseline-capture" selected/s',
            $pair,
            'The second-newest capture must be selected as the baseline.',
        );
        self::assertMatchesRegularExpression(
            '/name="target".*value="target-capture" selected/s',
            $pair,
            'The newest capture must be selected as the target.',
        );
        self::assertStringNotContainsString(
            'yii-debug-history-compare-title',
            $single,
            'A single capture must not offer an unusable comparison form.',
        );
    }

    public function testHistoryGridRendersPagerAndMarksTheCurrentPage(): void
    {
        [, $manifest] = $this->comparison();

        $html = HistoryGridRenderer::render(
            $manifest,
            ['per-page' => '1', 'page' => '2'],
            '/developer/debug',
        );

        self::assertStringContainsString(
            '<ul class="yii-debug-pager">',
            $html,
            'Multiple pages must render the history pager.',
        );
        self::assertStringContainsString(
            '/developer/debug?per-page=1&amp;page=1',
            $html,
            'The pager must link to the first page while preserving the page size.',
        );
        self::assertStringContainsString(
            '<li class="yii-debug-pager-item is-active">',
            $html,
            'The current page must be marked as active.',
        );
        self::assertStringContainsString(
            '/developer/debug?per-page=1&amp;page=2',
            $html,
            'The pager must render a link for the current page.',
        );
    }

    public function testRegisteredPanelsUseCustomLabelsAndOnlyAvailableCaptureLinks(): void
    {
        $baseline = new DebugSnapshot(
            RequestSummary::create('baseline'),
            [
                'log' => [],
                'custom' => [],
                'unknown' => [],
            ],
            [],
        );
        $target = new DebugSnapshot(
            RequestSummary::create('target'),
            ['log' => []],
            [
                'custom' => PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('Failed')),
            ],
        );

        $html = HistoryComparisonRenderer::renderWithPanels(
            HistoryComparison::fromSnapshots($baseline, $target),
            ['baseline' => $baseline->summary, 'target' => $target->summary],
            '/developer/debug',
            ['log' => 'Logs', 'custom' => '<Custom & panel>'],
        );

        self::assertStringContainsString(
            '&lt;Custom &amp; panel&gt;',
            $html,
            'Registered panel names must be escaped.',
        );
        self::assertStringContainsString(
            'tag=baseline&amp;panel=log',
            $html,
            'Captured Logs must be navigable.',
        );
        self::assertStringContainsString(
            'tag=target&amp;panel=custom',
            $html,
            'Registered failed captures must expose their diagnostic page.',
        );
        self::assertStringNotContainsString(
            'panel=unknown',
            $html,
            'Historical unregistered panels must not produce broken links.',
        );
    }

    /**
     * @param array<string, array<string, mixed>> $baselinePanels Panel payloads captured by the baseline.
     * @param array<string, string> $panelLabels Display names indexed by panel ID.
     */
    #[DataProviderExternal(HistoryComparisonRendererProvider::class, 'resultFragments')]
    public function testResultsContainRenderedFragment(
        RequestSummary $baseline,
        RequestSummary $target,
        array $baselinePanels,
        array $panelLabels,
        string $routePrefix,
        string $expected,
    ): void {
        self::assertStringContainsString(
            $expected,
            HistoryComparisonRenderer::render(
                HistoryComparison::fromSnapshots(
                    new DebugSnapshot($baseline, $baselinePanels, []),
                    new DebugSnapshot($target, [], []),
                    $panelLabels,
                ),
                ['target' => $target, 'baseline' => $baseline],
                $routePrefix,
            ),
            'Comparison page must contain the expected markup.',
        );
    }

    public function testResultsEscapeCaptureDataAndLinkOnlySupportedPanels(): void
    {
        [$comparison, $manifest] = $this->comparison();

        $html = HistoryComparisonRenderer::render(
            $comparison,
            $manifest,
            '/developer/debug',
        );

        self::assertStringContainsString(
            'https://example.test/baseline?value=&lt;script&gt;',
            $html,
            'Captured URLs must remain escaped in selection options and overview cards.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Captured data must not introduce executable markup.',
        );
        self::assertStringContainsString(
            '/developer/debug/view?tag=baseline-capture&amp;panel=config',
            $html,
            'Baseline overview and supported Configuration rows must deep-link through the configured prefix.',
        );
        self::assertStringContainsString(
            '/developer/debug/view?tag=target-capture&amp;panel=config',
            $html,
            'Target overview and supported Configuration rows must deep-link through the configured prefix.',
        );
        self::assertStringContainsString(
            '/developer/debug/view?tag=baseline-capture&amp;panel=request',
            $html,
            'Captured baseline Request data must deep-link to the built-in panel.',
        );
        self::assertStringContainsString(
            '/developer/debug/view?tag=target-capture&amp;panel=request',
            $html,
            'Captured target Request data must deep-link to the built-in panel.',
        );
        self::assertStringNotContainsString(
            'panel=profiling',
            $html,
            'Metrics must not link to an unavailable Profiling panel.',
        );
        self::assertStringNotContainsString(
            'panel=db',
            $html,
            'Metrics must not link to an unavailable Database panel.',
        );
        self::assertStringNotContainsString(
            'panel=mail',
            $html,
            'Metrics must not link to an unavailable Mail panel.',
        );
    }

    public function testResultsExplainWhenNeitherCaptureContainsPanelPayloads(): void
    {
        [, $manifest] = $this->comparison();

        $comparison = HistoryComparison::fromSnapshots(
            new DebugSnapshot($manifest['baseline-capture'], [], []),
            new DebugSnapshot($manifest['target-capture'], [], []),
        );
        $html = HistoryComparisonRenderer::render(
            $comparison,
            $manifest,
            '/developer/debug',
        );

        self::assertStringContainsString(
            '<span class="yii-debug-section-count">0</span>',
            $html,
            'Panel structure heading must report that no panel payloads were captured.',
        );
        self::assertStringContainsString(
            'Neither capture contains panel payloads to compare.',
            $html,
            'The comparison must explain why panel structure has no rows.',
        );
        self::assertStringNotContainsString(
            'Panel structure comparison',
            $html,
            'An empty panel comparison must not render an empty seven-column table.',
        );
    }

    public function testResultsRenderFailureMissingAndUnsupportedPanelStatesAndTruncateLongUrls(): void
    {
        $longUrl = 'https://example.test/' . str_repeat('segment-', 12);

        $baseline = RequestSummary::create('baseline')
            ->withRequest($longUrl, 'GET', '127.0.0.1', 1.0);
        $target = RequestSummary::create('target')
            ->withRequest('https://example.test/target', 'GET', '127.0.0.1', 2.0);
        $failure = PanelFailure::fromThrowable(
            PanelFailure::CAPTURE,
            new RuntimeException('Capture failed.'),
        );
        $comparison = HistoryComparison::fromSnapshots(
            new DebugSnapshot(
                $baseline,
                ['custom' => ['value' => true]],
                ['failed' => $failure],
            ),
            new DebugSnapshot($target, [], []),
            ['custom' => 'Custom', 'failed' => 'Failed panel'],
        );
        $html = HistoryComparisonRenderer::render(
            $comparison,
            ['target' => $target, 'baseline' => $baseline],
            '/developer/debug',
        );

        self::assertStringContainsString(
            substr($longUrl, 0, 69) . '...',
            $html,
            'Long captured URLs must be truncated in the comparison selectors.',
        );
        self::assertStringContainsString(
            'yii-debug-badge-danger">Failed</span>',
            $html,
            'A failed panel capture must use the danger state badge.',
        );
        self::assertStringContainsString(
            'yii-debug-badge-muted">Not captured</span>',
            $html,
            'A missing panel capture must use the muted state badge.',
        );
        self::assertStringContainsString(
            'class="yii-debug-not-set">—</span>',
            $html,
            'A missing panel capture must include the not-set marker.',
        );
        self::assertStringNotContainsString(
            'panel=custom',
            $html,
            'Unsupported custom panels must render their state without a deep link.',
        );
        self::assertStringNotContainsString(
            'panel=failed',
            $html,
            'Failed custom panels must render their state without a deep link.',
        );
    }

    public function testResultsRenderTheFullPageForIdenticalCaptures(): void
    {
        $baseline = RequestSummary::create('baseline');
        $target = RequestSummary::create('target');

        self::assertSame(
            <<<HTML
            <h1 class="yii-debug-hero-title">
            Compare captures
            </h1><section class="yii-debug-section" aria-labelledby="yii-debug-compare-selection">
            <h2 class="yii-debug-section-title" id="yii-debug-compare-selection">
            <span class="yii-debug-section-mark">01</span>Selection
            </h2><form class="yii-debug-compare-form" action="/developer/debug/compare" method="get">
            <div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-baseline">Baseline capture</label><select class="yii-debug-select" id="yii-debug-compare-baseline" name="baseline" required>
            <option value="target">
            time unavailable · UNKNOWN ·  · target
            </option>
            <option value="baseline" selected>
            time unavailable · UNKNOWN ·  · baseline
            </option>
            </select>
            </div><div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-target">Target capture</label><select class="yii-debug-select" id="yii-debug-compare-target" name="target" required>
            <option value="target" selected>
            time unavailable · UNKNOWN ·  · target
            </option>
            <option value="baseline">
            time unavailable · UNKNOWN ·  · baseline
            </option>
            </select>
            </div><div class="yii-debug-field">
            <button class="yii-debug-btn yii-debug-btn-primary" type="submit">Compare captures</button>
            </div>
            </form>
            </section><section class="yii-debug-section" aria-labelledby="yii-debug-compare-overview-title">
            <h2 class="yii-debug-section-title" id="yii-debug-compare-overview-title">
            <span class="yii-debug-section-mark">02</span>Capture overview
            </h2><div class="yii-debug-compare-overview">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Baseline</span><a class="yii-debug-readout-value" href="/developer/debug/view?tag=baseline&amp;panel=config">baseline</a><span class="yii-debug-readout-meta"> · 0</span><span class="yii-debug-muted"></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Target</span><a class="yii-debug-readout-value" href="/developer/debug/view?tag=target&amp;panel=config">target</a><span class="yii-debug-readout-meta"> · 0</span><span class="yii-debug-muted"></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Result</span><span class="yii-debug-readout-value">Identical</span><span class="yii-debug-readout-meta">Summary and panel structure</span>
            </article>
            </div>
            </section><section class="yii-debug-section" aria-labelledby="yii-debug-compare-metrics-title">
            <h2 class="yii-debug-section-title" id="yii-debug-compare-metrics-title">
            <span class="yii-debug-section-mark">03</span>Request metrics
            </h2><div aria-label="Request summary comparison" role="region" tabindex="0" class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-compare-grid">
            <caption class="yii-debug-sr-only">Request summary comparison</caption>
            <thead>
            <tr>
            <th scope="col">Metric</th>
            <th scope="col">Baseline</th>
            <th scope="col">Target</th>
            <th scope="col">Delta</th>
            </tr>
            </thead>
            <tbody>
            <tr>
            <td>Status</td>
            <td class="yii-debug-cell-mono">Not captured</td>
            <td class="yii-debug-cell-mono">Not captured</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>Method</td>
            <td class="yii-debug-cell-mono"></td>
            <td class="yii-debug-cell-mono"></td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>AJAX</td>
            <td class="yii-debug-cell-mono">No</td>
            <td class="yii-debug-cell-mono">No</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>Duration</td>
            <td class="yii-debug-cell-mono">Not captured</td>
            <td class="yii-debug-cell-mono">Not captured</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>Peak memory</td>
            <td class="yii-debug-cell-mono">Not captured</td>
            <td class="yii-debug-cell-mono">Not captured</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>SQL queries</td>
            <td class="yii-debug-cell-mono">0</td>
            <td class="yii-debug-cell-mono">0</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>Mail messages</td>
            <td class="yii-debug-cell-mono">0</td>
            <td class="yii-debug-cell-mono">0</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            <tr>
            <td>Excessive DB callers</td>
            <td class="yii-debug-cell-mono">0</td>
            <td class="yii-debug-cell-mono">0</td>
            <td><span class="yii-debug-delta-neutral">No change</span></td>
            </tr>
            </tbody>
            </table>
            </div>
            </section><section class="yii-debug-section" aria-labelledby="yii-debug-compare-panels-title">
            <h2 class="yii-debug-section-title" id="yii-debug-compare-panels-title">
            <span class="yii-debug-section-mark">04</span>Panel structure<span class="yii-debug-section-count">0</span>
            </h2><p class="yii-debug-muted">
            Neither capture contains panel payloads to compare.
            </p>
            </section>
            HTML,
            HistoryComparisonRenderer::render(
                HistoryComparison::fromSnapshots(
                    new DebugSnapshot($baseline, [], []),
                    new DebugSnapshot($target, [], []),
                ),
                ['target' => $target, 'baseline' => $baseline],
                '/developer/debug',
            ),
            'Order: heading, selection, overview, metrics, panels; matching captures report `Identical`.',
        );
    }

    public function testSameSecondSelectionOptionsRemainDistinctAndEscaped(): void
    {
        $first = RequestSummary::create('6a9ec2295ddcd414251546')
            ->withRequest('/<script>', 'GET', '', 1000.1);
        $second = RequestSummary::create('6a9ec22932a3c954772352')
            ->withRequest('/<script>', 'GET', '', 1000.2);
        $html = HistoryComparisonRenderer::renderForm(
            [
                $first->tag => $first,
                $second->tag => $second,
            ],
            $first->tag,
            $second->tag,
            '/debug',
        );

        self::assertStringContainsString(
            "/&lt;script&gt; · {$first->tag}",
            $html,
            'The first option must show the full unique tag and escaped URL.',
        );
        self::assertStringContainsString(
            "/&lt;script&gt; · {$second->tag}",
            $html,
            'The second option must remain distinguishable.',
        );
    }

    /**
     * @return array{
     *     HistoryComparison,
     *     array{'target-capture': RequestSummary, 'baseline-capture': RequestSummary}
     * }
     */
    private function comparison(): array
    {
        $baseline = RequestSummary::create('baseline-capture')
            ->withRequest('https://example.test/baseline?value=<script>', 'GET', '127.0.0.1', 1_725_000_700.0)
            ->withResponse(200)
            ->withProfiling(0.010, 1_048_576);
        $target = RequestSummary::create('target-capture')
            ->withRequest('https://example.test/target', 'POST', '127.0.0.1', 1_725_000_756.0, true)
            ->withResponse(500)
            ->withProfiling(0.015, 2_097_152);
        $comparison = HistoryComparison::fromSnapshots(
            new DebugSnapshot(
                $baseline,
                [
                    'request' => ['status' => 200],
                    'config' => ['environment' => 'dev'],
                ],
                [],
            ),
            new DebugSnapshot(
                $target,
                [
                    'request' => ['status' => 500],
                    'config' => ['environment' => 'dev'],
                ],
                [],
            ),
            ['request' => 'Request', 'config' => 'Configuration'],
        );

        return [
            $comparison,
            [
                'target-capture' => $target,
                'baseline-capture' => $baseline,
            ],
        ];
    }

    private function pageRenderer(): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-history-comparison-renderer-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));

        return new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            new ToolbarOptions(routePrefix: '/developer/debug'),
        );
    }
}
