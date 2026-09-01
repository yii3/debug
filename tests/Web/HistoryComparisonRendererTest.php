<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Comparison\HistoryComparison;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Web\{DebugPageRenderer, HistoryComparisonRenderer, HistoryGridRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

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

        return (
            new DebugPageRenderer(
                new WebView(),
                $assetManager,
                new ConfigDataFactory(),
                $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            )
        )->withRoutePrefix('/developer/debug');
    }
}
