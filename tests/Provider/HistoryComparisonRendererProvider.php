<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use PHPForge\Debug\Storage\RequestSummary;

/**
 * Data provider for {@see \Yii3\Debug\Tests\Web\HistoryComparisonRendererTest} test cases.
 */
final class HistoryComparisonRendererProvider
{
    /**
     * @return iterable<
     *     string,
     *     array{
     *         RequestSummary,
     *         RequestSummary,
     *         array<string, array<string, mixed>>,
     *         array<string, string>,
     *         string,
     *         string,
     *     }
     * >
     */
    public static function resultFragments(): iterable
    {
        yield 'captured and missing panel states' => [
            RequestSummary::create('baseline'),
            RequestSummary::create('target'),
            ['request' => ['status' => 200]],
            ['request' => 'Request'],
            '/developer/debug/',
            <<<HTML
            <tr>
            <td>Request</td>
            <td><span class="yii-debug-badge yii-debug-badge-success">Captured</span> <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-sm" href="/developer/debug/view?tag=baseline&amp;panel=request">Open panel</a></td>
            <td><span class="yii-debug-badge yii-debug-badge-muted">Not captured</span> <span class="yii-debug-not-set">—</span></td>
            <td class="yii-debug-cell-numeric">0</td>
            <td class="yii-debug-cell-numeric">1</td>
            <td class="yii-debug-cell-numeric">0</td>
            <td class="yii-debug-cell-numeric">0</td>
            </tr>
            HTML,
        ];
        yield 'changed verdict and capture links' => [
            RequestSummary::create('baseline'),
            RequestSummary::create('target'),
            ['request' => ['status' => 200]],
            ['request' => 'Request'],
            '/developer/debug/',
            <<<HTML
            <div class="yii-debug-compare-overview">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Baseline</span><a class="yii-debug-readout-value" href="/developer/debug/view?tag=baseline&amp;panel=config">baseline</a><span class="yii-debug-readout-meta"> · 0</span><span class="yii-debug-muted"></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Target</span><a class="yii-debug-readout-value" href="/developer/debug/view?tag=target&amp;panel=config">target</a><span class="yii-debug-readout-meta"> · 0</span><span class="yii-debug-muted"></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-label">Result</span><span class="yii-debug-readout-value">Changed</span><span class="yii-debug-readout-meta">Summary and panel structure</span>
            </article>
            </div>
            HTML,
        ];
        yield 'encoded captured methods' => [
            RequestSummary::create('baseline')->withRequest('/baseline', 'B<1>', '', 1.0),
            RequestSummary::create('target')->withRequest('/target', 'T<2>', '', 2.0),
            [],
            [],
            '/developer/debug',
            <<<HTML
            <tr>
            <td>Method</td>
            <td class="yii-debug-cell-mono">B&lt;1&gt;</td>
            <td class="yii-debug-cell-mono">T&lt;2&gt;</td>
            <td><span class="yii-debug-delta-neutral">Changed</span></td>
            </tr>
            HTML,
        ];
        yield 'trailing-slash form action' => [
            RequestSummary::create('baseline'),
            RequestSummary::create('target'),
            [],
            [],
            '/developer/debug/',
            <<<HTML
            <form class="yii-debug-compare-form" action="/developer/debug/compare" method="get">
            HTML,
        ];
    }
}
