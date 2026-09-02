<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use Closure;
use InvalidArgumentException;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPForge\Vite\Vite;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Panel\{ExtensionPanelInterface, InertiaPanel, ProfilingPanel, RequestPanel, VitePanel};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};
use Yiisoft\View\WebView;

use function count;
use function date;
use function explode;
use function implode;
use function ini_get;
use function php_uname;
use function preg_match;
use function sys_get_temp_dir;

use const PHP_SAPI;
use const PHP_VERSION;

/**
 * Integration tests for the two Debug Core brand pages.
 */
final class DebugPageRendererTest extends TestCase
{
    public function testConfigOmitsExtensionsGroupForEmptyInertiaCapture(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            [
                'inertia' => InertiaSnapshot::capture(
                    null,
                    null,
                    [],
                    [],
                    200,
                )->jsonSerialize(),
            ],
            [],
        );
        $renderer = $this->rendererWithInertia();
        [$readoutGrid, $phpExtensions, $installedExtensions] = self::expectedConfigFragments();
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'An empty Inertia capture must match the complete Configuration document.',
        );
    }

    public function testConfigShowsCapturedInertiaUnderExtensions(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            ['inertia' => $this->inertiaPayload()],
            [],
        );
        $renderer = $this->rendererWithInertia();
        [$readoutGrid, $phpExtensions, $installedExtensions] = self::expectedConfigFragments();
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav><section class="yii-debug-side-section yii-debug-nav-group" aria-label="Extensions">
            <header class="yii-debug-side-section-title">
            Extensions
            </header><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Extensions debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=inertia" title="View Inertia panel">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m5 5l7 7l-7 7"/><path d="m12 5l7 7l-7 7"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Inertia
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </section>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'Captured Inertia activity must match the complete Configuration document.',
        );
    }

    public function testConfigShowsCapturedViteUnderExtensions(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            ['vite' => $this->vitePayload()],
            [],
        );
        $renderer = $this->rendererWithVite();
        [$readoutGrid, $phpExtensions, $installedExtensions] = self::expectedConfigFragments();
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav><section class="yii-debug-side-section yii-debug-nav-group" aria-label="Extensions">
            <header class="yii-debug-side-section-title">
            Extensions
            </header><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Extensions debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=vite" title="View Vite panel">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M20 4l-2 14.5l-6 2l-6-2l-2-14.5h16"/><path d="M7.5 8h3v8l-2-1"/><path d="M16.5 8H14a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h1.423a.5.5 0 0 1 .495.57l-.418 2.93l-2 .5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Vite
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </section>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'Captured Vite configuration must match the complete Configuration document.',
        );
    }

    public function testConfigShowsFailedInertiaCaptureUnderExtensions(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            [],
            [
                'inertia' => PanelFailure::fromThrowable(
                    PanelFailure::CAPTURE,
                    new RuntimeException('Inertia capture failed.'),
                ),
            ],
        );
        $renderer = $this->rendererWithInertia();
        [$readoutGrid, $phpExtensions, $installedExtensions] = self::expectedConfigFragments();
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav><section class="yii-debug-side-section yii-debug-nav-group" aria-label="Extensions">
            <header class="yii-debug-side-section-title">
            Extensions
            </header><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Extensions debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=inertia" title="View Inertia panel">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m5 5l7 7l-7 7"/><path d="m12 5l7 7l-7 7"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Inertia
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </section>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'A failed Inertia capture must match the complete Configuration document.',
        );
    }

    public function testConfigurationMethodsPreserveExistingSettings(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            ['request' => $this->requestPayload()],
            [],
        );
        $original = $this->rendererWithPanels('page-renderer-immutability-assets', []);
        $withPanels = $original
            ->withExtensionPanels([new RequestPanel()]);
        $configured = $withPanels
            ->withRoutePrefix('/developer/debug/');
        $panelsLast = $original
            ->withRoutePrefix('/developer/debug/')
            ->withExtensionPanels([new RequestPanel()]);

        self::assertFalse(
            $original->hasExtensionPanel('request'),
            'Configuration methods must not register panels on the original renderer.',
        );
        self::assertTrue(
            $configured->hasExtensionPanel('request'),
            'Route configuration must retain registered extension panels.',
        );

        [$readoutGrid, $phpExtensions, $installedExtensions] = self::expectedConfigFragments();
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $withPanels->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=request" title="View Request panel">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 256 256"><path fill="currentColor" d="M227.32 28.68a16 16 0 0 0-15.66-4.08h-.15L19.57 82.84a16 16 0 0 0-2.49 29.8L102 154l41.3 84.87a15.86 15.86 0 0 0 14.44 9.13q.69 0 1.38-.06a15.88 15.88 0 0 0 14-11.51l58.2-191.94v-.15a16 16 0 0 0-4-15.66m-69.49 203.17l-.05.14v-.07l-40.06-82.3l48-48a8 8 0 0 0-11.31-11.31l-48 48l-82.33-40.06h-.07h.14L216 40Z"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Request
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'Route configuration must not mutate the renderer it was copied from.',
        );

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $configured->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/developer/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/developer/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/developer/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/developer/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/developer/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/developer/debug/view?tag=request-1&amp;panel=request" title="View Request panel">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 256 256"><path fill="currentColor" d="M227.32 28.68a16 16 0 0 0-15.66-4.08h-.15L19.57 82.84a16 16 0 0 0-2.49 29.8L102 154l41.3 84.87a15.86 15.86 0 0 0 14.44 9.13q.69 0 1.38-.06a15.88 15.88 0 0 0 14-11.51l58.2-191.94v-.15a16 16 0 0 0-4-15.66m-69.49 203.17l-.05.14v-.07l-40.06-82.3l48-48a8 8 0 0 0-11.31-11.31l-48 48l-82.33-40.06h-.07h.14L216 40Z"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Request
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/developer/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'Panel configuration must retain the route prefix when panels are configured first.',
        );

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $panelsLast->config('request-1', 'light', $manifest, $snapshot),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/developer/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/developer/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/developer/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/developer/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/developer/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/developer/debug/view?tag=request-1&amp;panel=request" title="View Request panel">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 256 256"><path fill="currentColor" d="M227.32 28.68a16 16 0 0 0-15.66-4.08h-.15L19.57 82.84a16 16 0 0 0-2.49 29.8L102 154l41.3 84.87a15.86 15.86 0 0 0 14.44 9.13q.69 0 1.38-.06a15.88 15.88 0 0 0 14-11.51l58.2-191.94v-.15a16 16 0 0 0-4-15.66m-69.49 203.17l-.05.14v-.07l-40.06-82.3l48-48a8 8 0 0 0-11.31-11.31l-48 48l-82.33-40.06h-.07h.14L216 40Z"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Request
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/developer/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'Panel configuration must retain the route prefix when the route is configured first.',
        );
    }

    public function testConfigUsesCoreRendererAndDarkTheme(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $renderer = $this->renderer();
        [$readoutGrid, $phpExtensions, $installedExtensions] = self::expectedConfigFragments();
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->config('request-1', 'dark', $manifest),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="dark">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Configuration — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to light theme" aria-label="Switch to light theme" aria-pressed="true" data-yii-debug-theme-toggle="true" data-current-theme="dark" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-sr-only">
            Configuration
            </h1>{$readoutGrid}{$phpExtensions}<section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>{$installedExtensions}<a class="yii-debug-cta" href="/debug/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The dark Configuration page must match the complete rendered document.',
        );
    }

    public function testExtensionPanelIdentifiersAreNormalizedBeforeDuplicateValidation(): void
    {
        $padded = self::createStub(ExtensionPanelInterface::class);

        $padded
            ->method('id')
            ->willReturn(' request ');

        $normalized = self::createStub(ExtensionPanelInterface::class);

        $normalized
            ->method('id')
            ->willReturn('request');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Duplicate debug extension panel ID: request.',
        );

        $this->rendererWithPanels('page-renderer-normalized-panel-assets', [])
            ->withExtensionPanels([$padded, $normalized]);
    }

    public function testHistoryAppliesRequestFilters(): void
    {
        $tab = "\t";
        $renderer = $this->renderer();
        $manifest = $this->manifest();
        $phpVersion = PHP_VERSION;
        $requestOneTime = date('H:i:s', 1_725_000_756);
        $requestTwoDateTime = date('Y-m-d H:i:s', 1_725_000_700);
        $requestTwoTime = date('H:i:s', 1_725_000_700);

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->history(
                $manifest,
                ['Debug' => ['method' => 'POST']],
                'light',
            ),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Request history — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Newest request" data-yii-debug-history-cursor="true">
            <header class="yii-debug-side-section-title">
            Newest request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestOneTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request" data-yii-debug-cursor="newest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request" data-yii-debug-cursor="newer"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Older request" aria-label="Older captured request" data-yii-debug-cursor="older"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Oldest request" aria-label="Oldest captured request" data-yii-debug-cursor="oldest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></button>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug" title="View request history" aria-current="page">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            Request history
            </h1><header class="yii-debug-grid-summary">
            <span><strong>2</strong> captured requests</span><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-2xx" href="/debug?Debug%5BstatusCode%5D=200" title="Filter to 2xx responses (sample 200)"><strong>1</strong> 2xx</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-4xx" href="/debug?Debug%5BstatusCode%5D=404" title="Filter to 4xx responses (sample 404)"><strong>1</strong> 4xx</a><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50" selected>
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all">
            All
            </option>
            </select></label>
            </header><section class="yii-debug-section" aria-labelledby="yii-debug-history-compare-title">
            <h2 class="yii-debug-section-title" id="yii-debug-history-compare-title">
            <span class="yii-debug-section-mark">Compare</span>Capture changes
            </h2><form class="yii-debug-compare-form" action="/debug/compare" method="get">
            <div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-baseline">Baseline capture</label><select class="yii-debug-select" id="yii-debug-compare-baseline" name="baseline" required>
            <option value="request-1">
            {$requestOneTime} · GET · https://example.test/?page=2 · request-
            </option>
            <option value="request-2" selected>
            {$requestTwoTime} · POST · https://example.test/missing · request-
            </option>
            </select>
            </div><div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-target">Target capture</label><select class="yii-debug-select" id="yii-debug-compare-target" name="target" required>
            <option value="request-1" selected>
            {$requestOneTime} · GET · https://example.test/?page=2 · request-
            </option>
            <option value="request-2">
            {$requestTwoTime} · POST · https://example.test/missing · request-
            </option>
            </select>
            </div><div class="yii-debug-field">
            <button class="yii-debug-btn yii-debug-btn-primary" type="submit">Compare captures</button>
            </div>
            </form>
            </section><div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug" title="Remove this filter" aria-label="Remove method: POST filter"><span class="yii-debug-active-filter-attr">method</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">POST</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-grid yii-debug-grid-history">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th class="yii-debug-col-num" scope="col">
            #
            </th><th class="yii-debug-col-id" scope="col">
            ID
            </th><th scope="col">
            Time
            </th><th scope="col">
            Duration
            </th><th scope="col">
            Memory
            </th><th class="yii-debug-col-ip" scope="col">
            IP
            </th><th scope="col">
            Method
            </th><th scope="col">
            Ajax
            </th><th scope="col">
            URL
            </th>
            </tr><tr class="filters">
            <td class="yii-debug-col-num">
            </td><td class="yii-debug-col-id">
            <input class="yii-debug-input yii-debug-col-id-input" name="Debug[tag]" type="text">
            </td><td>
            </td><td>
            </td><td>
            </td><td class="yii-debug-col-ip">
            <input class="yii-debug-input" name="Debug[ip]" type="text">
            </td><td>
            <select class="yii-debug-select" name="Debug[method]">
            <option>
            </option>
            <option value="GET">
            GET
            </option>
            <option value="POST" selected>
            POST
            </option>
            <option value="PUT">
            PUT
            </option>
            <option value="PATCH">
            PATCH
            </option>
            <option value="DELETE">
            DELETE
            </option>
            </select>
            </td><td>
            <select class="yii-debug-select" name="Debug[ajax]">
            <option selected>
            </option>
            <option value="0">
            No
            </option>
            <option value="1">
            Yes
            </option>
            </select>
            </td><td>
            <input class="yii-debug-input" name="Debug[url]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr class="yii-debug-row-danger" data-yii-debug-tag="request-2" data-yii-debug-method="POST" data-yii-debug-url="https://example.test/missing" data-yii-debug-status="404" data-yii-debug-time="{$requestTwoTime}" data-yii-debug-ajax="1">
            <td class="yii-debug-col-num">
            1
            </td><td class="yii-debug-col-id">
            <a class="yii-debug-tag-link" href="/debug/view?tag=request-2&amp;panel=auto">request-2</a>
            </td><td>
            <span class="yii-debug-nowrap" title="{$requestTwoDateTime}">{$requestTwoTime}</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">15 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">2.000 MB</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-col-ip">
            127.0.0.1
            </td><td>
            <span class="yii-debug-method yii-debug-verb-post">POST</span>
            </td><td>
            Yes
            </td><td>
            <span class="yii-debug-url-cell" title="https://example.test/missing">https://example.test/missing</span>
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-1 of 1 items.</span>
            </div>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The filtered History page must match the complete rendered document.',
        );
    }

    public function testHistoryRendersSummaryFiltersRowsAndNewestRequestSidebar(): void
    {
        $tab = "\t";
        $renderer = $this->renderer();
        $manifest = $this->manifest();
        $phpVersion = PHP_VERSION;
        $requestOneDateTime = date('Y-m-d H:i:s', 1_725_000_756);
        $requestOneTime = date('H:i:s', 1_725_000_756);
        $requestTwoDateTime = date('Y-m-d H:i:s', 1_725_000_700);
        $requestTwoTime = date('H:i:s', 1_725_000_700);

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->history($manifest, [], 'dark'),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="dark">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Request history — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to light theme" aria-label="Switch to light theme" aria-pressed="true" data-yii-debug-theme-toggle="true" data-current-theme="dark" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Newest request" data-yii-debug-history-cursor="true">
            <header class="yii-debug-side-section-title">
            Newest request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestOneTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request" data-yii-debug-cursor="newest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request" data-yii-debug-cursor="newer"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Older request" aria-label="Older captured request" data-yii-debug-cursor="older"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Oldest request" aria-label="Oldest captured request" data-yii-debug-cursor="oldest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></button>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug" title="View request history" aria-current="page">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            Request history
            </h1><header class="yii-debug-grid-summary">
            <span><strong>2</strong> captured requests</span><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-2xx" href="/debug?Debug%5BstatusCode%5D=200" title="Filter to 2xx responses (sample 200)"><strong>1</strong> 2xx</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-4xx" href="/debug?Debug%5BstatusCode%5D=404" title="Filter to 4xx responses (sample 404)"><strong>1</strong> 4xx</a><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50" selected>
            50
            </option>
            <option value="100">
            100
            </option>
            <option value="all">
            All
            </option>
            </select></label>
            </header><section class="yii-debug-section" aria-labelledby="yii-debug-history-compare-title">
            <h2 class="yii-debug-section-title" id="yii-debug-history-compare-title">
            <span class="yii-debug-section-mark">Compare</span>Capture changes
            </h2><form class="yii-debug-compare-form" action="/debug/compare" method="get">
            <div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-baseline">Baseline capture</label><select class="yii-debug-select" id="yii-debug-compare-baseline" name="baseline" required>
            <option value="request-1">
            {$requestOneTime} · GET · https://example.test/?page=2 · request-
            </option>
            <option value="request-2" selected>
            {$requestTwoTime} · POST · https://example.test/missing · request-
            </option>
            </select>
            </div><div class="yii-debug-field">
            <label class="yii-debug-label" for="yii-debug-compare-target">Target capture</label><select class="yii-debug-select" id="yii-debug-compare-target" name="target" required>
            <option value="request-1" selected>
            {$requestOneTime} · GET · https://example.test/?page=2 · request-
            </option>
            <option value="request-2">
            {$requestTwoTime} · POST · https://example.test/missing · request-
            </option>
            </select>
            </div><div class="yii-debug-field">
            <button class="yii-debug-btn yii-debug-btn-primary" type="submit">Compare captures</button>
            </div>
            </form>
            </section><div class="yii-debug-grid yii-debug-grid-history">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th class="yii-debug-col-num" scope="col">
            #
            </th><th class="yii-debug-col-id" scope="col">
            ID
            </th><th scope="col">
            Time
            </th><th scope="col">
            Duration
            </th><th scope="col">
            Memory
            </th><th class="yii-debug-col-ip" scope="col">
            IP
            </th><th scope="col">
            Method
            </th><th scope="col">
            Ajax
            </th><th scope="col">
            URL
            </th>
            </tr><tr class="filters">
            <td class="yii-debug-col-num">
            </td><td class="yii-debug-col-id">
            <input class="yii-debug-input yii-debug-col-id-input" name="Debug[tag]" type="text">
            </td><td>
            </td><td>
            </td><td>
            </td><td class="yii-debug-col-ip">
            <input class="yii-debug-input" name="Debug[ip]" type="text">
            </td><td>
            <select class="yii-debug-select" name="Debug[method]">
            <option selected>
            </option>
            <option value="GET">
            GET
            </option>
            <option value="POST">
            POST
            </option>
            <option value="PUT">
            PUT
            </option>
            <option value="PATCH">
            PATCH
            </option>
            <option value="DELETE">
            DELETE
            </option>
            </select>
            </td><td>
            <select class="yii-debug-select" name="Debug[ajax]">
            <option selected>
            </option>
            <option value="0">
            No
            </option>
            <option value="1">
            Yes
            </option>
            </select>
            </td><td>
            <input class="yii-debug-input" name="Debug[url]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr data-yii-debug-tag="request-1" data-yii-debug-method="GET" data-yii-debug-url="https://example.test/?page=2" data-yii-debug-status="200" data-yii-debug-time="{$requestOneTime}">
            <td class="yii-debug-col-num">
            1
            </td><td class="yii-debug-col-id">
            <a class="yii-debug-tag-link" href="/debug/view?tag=request-1&amp;panel=auto">request-1</a>
            </td><td>
            <span class="yii-debug-nowrap" title="{$requestOneDateTime}">{$requestOneTime}</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 60%;'><span class="yii-debug-gauge-value">9 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 54.613%;'><span class="yii-debug-gauge-value">1.092 MB</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-col-ip">
            127.0.0.1
            </td><td>
            <span class="yii-debug-method yii-debug-verb-get">GET</span>
            </td><td>
            No
            </td><td>
            <span class="yii-debug-url-cell" title="https://example.test/?page=2">https://example.test/?page=2</span>
            </td>
            </tr><tr class="yii-debug-row-danger" data-yii-debug-tag="request-2" data-yii-debug-method="POST" data-yii-debug-url="https://example.test/missing" data-yii-debug-status="404" data-yii-debug-time="{$requestTwoTime}" data-yii-debug-ajax="1">
            <td class="yii-debug-col-num">
            2
            </td><td class="yii-debug-col-id">
            <a class="yii-debug-tag-link" href="/debug/view?tag=request-2&amp;panel=auto">request-2</a>
            </td><td>
            <span class="yii-debug-nowrap" title="{$requestTwoDateTime}">{$requestTwoTime}</span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">15 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td>
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">2.000 MB</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-col-ip">
            127.0.0.1
            </td><td>
            <span class="yii-debug-method yii-debug-verb-post">POST</span>
            </td><td>
            Yes
            </td><td>
            <span class="yii-debug-url-cell" title="https://example.test/missing">https://example.test/missing</span>
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-2 of 2 items.</span>
            </div>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The History page must match the complete rendered document.',
        );
    }

    public function testInertiaPanelIsActiveAndRequestNavigationRetainsPanel(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            ['inertia' => $this->inertiaPayload()],
            [],
        );
        $renderer = $this->rendererWithInertia();
        $phpVersion = PHP_VERSION;
        $requestOneTime = date('H:i:s', 1_725_000_756);

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->extension($snapshot, 'inertia', 'dark', $manifest),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="dark">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Inertia — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to light theme" aria-label="Switch to light theme" aria-pressed="true" data-yii-debug-theme-toggle="true" data-current-theme="dark" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestOneTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=inertia" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=inertia" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav><section class="yii-debug-side-section yii-debug-nav-group" aria-label="Extensions">
            <header class="yii-debug-side-section-title">
            Extensions
            </header><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Extensions debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug/view?tag=request-1&amp;panel=inertia" title="View Inertia panel" aria-current="page">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m5 5l7 7l-7 7"/><path d="m12 5l7 7l-7 7"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Inertia
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </section>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            GET /?page=2
            </h1><h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>Site/Index</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Inertia visit</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>1</strong> prop</span>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono">
            <tbody>
            <tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Component
            </th><td>
            Site/Index
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            URL
            </th><td>
            /
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Version
            </th><td>
            version-1
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Visit
            </th><td>
            Inertia visit
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Status
            </th><td>
            200
            </td>
            </tr>
            </tbody>
            </table>
            </div><h2>
            Props
            </h2><div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Prop
            </th><th scope="col">
            Origin
            </th><th scope="col">
            Type
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            <strong>appName</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-info">shared</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            string(16)
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            "Test application"
            </td>
            </tr>
            </tbody>
            </table>
            </div><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Raw payload</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <pre>
            {
                "component": "Site/Index",
                "props": {
                    "appName": "Test application"
                },
                "url": "/",
                "version": "version-1"
            }
            </pre>
            </div>
            </details>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The Inertia page must match the complete rendered document.',
        );
    }

    public function testPhpInfoUsesCoreRenderer(): void
    {
        $tab = "\t";
        $renderer = $this->renderer();
        $manifest = $this->manifest();
        $phpVersion = PHP_VERSION;
        $phpSapi = PHP_SAPI;
        $phpOs = php_uname('s') . ' ' . php_uname('r');
        $memoryLimit = ini_get('memory_limit');
        $requestOneTime = date('H:i:s', 1_725_000_756);

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->phpInfo('light', $manifest),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="light">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>PHP Info — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to dark theme" aria-label="Switch to dark theme" aria-pressed="false" data-yii-debug-theme-toggle="true" data-current-theme="light" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestOneTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=config" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <div class="yii-debug-page">
            <h1 class="yii-debug-hero-title">
            phpinfo
            </h1><div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="PHP version">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">PHP version</span>
            </header><div class="yii-debug-phpinfo-overview-hero-headline">
            <strong class="yii-debug-phpinfo-overview-hero-version">{$phpVersion}</strong><span class="yii-debug-phpinfo-overview-hero-mark" aria-hidden="true">php</span>
            </div><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            SAPI
            </dt><dd>
            <code>{$phpSapi}</code>
            </dd>
            </div><div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            OS
            </dt><dd>
            <code>{$phpOs}</code>
            </dd>
            </div><div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            Memory limit
            </dt><dd>
            <code>{$memoryLimit}</code>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The PHP Info page must match the complete rendered document.',
        );
    }

    public function testProfilingPanelIsPrimaryActiveAndReceivesQueryContext(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            ['profiling' => $this->profilingPayload()],
            [],
        );
        $renderer = $this->rendererWithProfiling();
        $queryParams = ['Profile' => ['category' => 'HomeAction']];
        $phpVersion = PHP_VERSION;
        $requestTime = date('H:i:s', 1_725_000_756);
        $profileDateTime = date('Y-m-d H:i:s.000', 1_725_000_756);
        $profileTime = date('H:i:s.000', 1_725_000_756);

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->extension(
                $snapshot,
                'profiling',
                'dark',
                $manifest,
                $queryParams,
            ),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="dark">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Profiling — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to light theme" aria-label="Switch to light theme" aria-pressed="true" data-yii-debug-theme-toggle="true" data-current-theme="dark" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=profiling" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=profiling" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug/view?tag=request-1&amp;panel=profiling" title="View Profiling panel" aria-current="page">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/><path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0-2 0m2.41-1.41L16 8m-9 4a5 5 0 0 1 5-5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Profiling
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            GET /?page=2
            </h1><h1 class="yii-debug-sr-only">
            Performance Profiling
            </h1><header class="yii-debug-grid-summary">
            <span><strong>1</strong> of 1 profile block</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>25 ms</strong> total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.000 MB</strong> peak</span><label class="yii-debug-grid-pagesize"><span class="yii-debug-grid-pagesize-label">Rows</span><select class="yii-debug-grid-pagesize-select" name="per-page" data-yii-debug-pagesize="true">
            <option value="10">
            10
            </option>
            <option value="25">
            25
            </option>
            <option value="50" selected>
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
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug/view?tag=request-1&amp;panel=profiling" title="Remove this filter" aria-label="Remove category: HomeAction filter"><span class="yii-debug-active-filter-attr">category</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">HomeAction</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug/view?tag=request-1&amp;panel=profiling" title="Clear all filters and show every row" aria-label="Clear all active filters">Clear all</a>
            </div><div class="yii-debug-grid yii-debug-grid-profile">
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=HomeAction&amp;sort=seq">Time</a>
            </th><th scope="col">
            <a class="desc" href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=HomeAction&amp;sort=duration">Duration</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=HomeAction&amp;sort=category">Category</a>
            </th><th scope="col">
            <a href="/debug/view?tag=request-1&amp;panel=profiling&amp;Profile%5Bcategory%5D=HomeAction&amp;sort=info">Info</a>
            </th>
            </tr><tr class="filters">
            <td>
            </td><td>
            </td><td>
            <input class="yii-debug-input" name="Profile[category]" type="text" value="HomeAction">
            </td><td>
            <input class="yii-debug-input" name="Profile[info]" type="text">
            </td>
            </tr>
            </thead><tbody>
            <tr>
            <td class="yii-debug-cell-mono yii-debug-nowrap">
            <span title="{$profileDateTime}">{$profileTime}</span>
            </td><td class="yii-debug-cell-numeric">
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">25.0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-fqcn">
            <span title="App\Web\HomeAction::__invoke"><span class="yii-debug-muted">App\Web\</span><wbr><strong>HomeAction::__invoke</strong></span>
            </td><td>
            Build home response
            </td>
            </tr>
            </tbody>
            </table>
            </div><div class="yii-debug-grid-footer">
            <span class="summary yii-debug-grid-count">Showing 1-1 of 1 items.</span>
            </div>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The Profiling page must match the complete rendered document.',
        );
    }

    public function testRequestPanelIsPrimaryActiveAndUsesTheCapturedSummary(): void
    {
        $tab = "\t";
        $manifest = $this->manifest();
        $snapshot = new DebugSnapshot(
            $manifest['request-1'],
            ['request' => $this->requestPayload()],
            [],
        );
        $renderer = $this->renderer();
        $phpVersion = PHP_VERSION;
        $requestOneTime = date('H:i:s', 1_725_000_756);

        [$peakMemory, $html] = self::renderWithPeakMemory(
            static fn(): string => $renderer->extension($snapshot, 'request', 'dark', $manifest),
        );

        self::assertSame(
            <<<HTML
            <!doctype html>
            <html lang="en" data-yii-debug-theme="dark">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="robots" content="none">
                <title>Request — Yii Debugger</title>
                <link rel="icon" type="image/svg+xml" href="/debug-assets/yii3-debug/test/svg/yii.svg">
                <link rel="stylesheet" href="/debug-assets/yii3-debug/test/dist/css/debug.min.css"></head>
            <body class="yii-debug">
            <div class="yii-debug-page default-view">
            <a class="yii-debug-skip-link" href="#yii-debug-main">Skip to debug content</a><header class="yii-debug-brand-bar">
            <a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="/debug"><span class="yii-debug-brand-icon"><svg xmlns:serif="http://www.serif.com/" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 650.832 800" style="enable-background:new 0 0 650.832 800;" xml:space="preserve">
            <style type="text/css">
            {$tab}.st0{fill:#40B3D8;}
            {$tab}.st1{fill:#83C933;}
            {$tab}.st2{fill-rule:evenodd;clip-rule:evenodd;fill:#F18A2A;}
            {$tab}.st3{fill:#7FB93C;}
            </style>
            <g id="Слой-1">
            {$tab}<g>
            {$tab}{$tab}<path class="st0" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c-0.033,0.112-15.486,83.388-43.271,143.639c-4.638,10.064-10.797,22.626-17.013,32.744l0.002,0.001    c-19.183,33.866-47.013,66.265-63.604,99.105c-16.449,32.546-19.501,64.78-17.97,101.427    c1.543,36.85,10.054,72.992,18.227,108.816c30.81-6.647,57.628-18.023,80.825-32.563c61.05-38.274,97.939-99.492,108.423-165.444    c0,0,0.511-2.678,0.738-5.945C484.509,545.078,482.945,528.996,481.395,508.535z"/>
            {$tab}{$tab}<path class="st1" d="M481.395,508.535c-5.4-71.26-28.382-117.45-39.574-143.628c-11.187-26.174-28.387-50.675-28.398-50.637    c0,0-0.004,0.017-0.004,0.018c0.001-0.011,0.004-0.023,0.004-0.023l-4.106-6.105c-90.029-126.38-262.69-189.479-408.57-130.996    c-7.024,88.584,34.046,241.004,183.875,283.517c60.572,18.634,109.076,13.803,168.521,29.966    c-0.003,0.002-0.003,0.004-0.005,0.005c0,0,60.424,21.058,95.576,52.638c15.813,14.202,31.646,32.891,30.851,55.121    C484.574,545.505,482.97,529.32,481.395,508.535z"/>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,233.564,496.875)">
            {$tab}{$tab}{$tab}<path class="st2" d="M182.406-256.769c-21.289-62.282-12.267-104.016,26.679-162.096c18.575-27.711,50.648-59.854,78.756-78.01     C401.25-425.808,443.183-293.436,401.476-170.5c-30.354,89.45-58.833,126.955-130.84,218.99     c8.393-98.569-26.297-166.901-60.359-242.831C201.61-213.659,189.622-235.652,182.406-256.769"/>
            {$tab}{$tab}</g>
            {$tab}{$tab}<g transform="matrix(1,0,0,1,245.403,498.558)">
            {$tab}{$tab}{$tab}<path class="st3" d="M234.164,99.853c0.795-22.23-15.038-40.919-30.852-55.121c-35.152-31.579-95.574-52.637-95.574-52.637     c6.217-10.118,12.375-22.681,17.012-32.745c27.786-60.252,43.239-143.526,43.272-143.639c0.011-0.038,17.21,24.464,28.398,50.637     c11.193,26.179,34.175,72.368,39.574,143.628C237.567,30.761,239.171,46.947,234.164,99.853z"/>
            {$tab}{$tab}</g>
            {$tab}</g>
            </g>
            </svg></span><span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">3</span></a><span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon"><svg xmlns="http://www.w3.org/2000/svg" width="1.99em" height="1em" viewBox="0 0 512 258"><title xmlns="">php-alt</title><path d="M116.448 54.116c22.287.187 38.436 6.612 48.449 19.266q15.018 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.346 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.15 3.968-33.433 3.967H50.15l-10.766 53.832H0L40.516 54.116zm335.893 0c22.287.187 38.437 6.612 48.45 19.266q15.017 18.98 9.916 51.849q-1.982 15.018-8.783 29.466c-4.347 9.633-10.387 18.32-18.133 26.066q-14.168 14.73-30.316 18.7q-16.152 3.968-33.433 3.967h-34l-10.766 53.832h-39.383L376.41 54.116zM258.775 0l-11.05 54.116h35.133q28.898.57 43.065 11.9c9.634 7.553 12.467 21.912 8.5 43.065L315.44 203.43h-39.666l18.133-90.099q2.83-14.168-1.7-20.116q-4.53-5.95-19.55-5.95l-31.449-.283l-23.233 116.448h-39.099L219.676 0zM85.848 86.415a79 79 0 0 1-6.516.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.966c18.133.187 33.246-1.604 45.333-5.383c12.087-3.967 20.212-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.013-3.02-22.57-4.437-37.683-4.25m335.894 0a79 79 0 0 1-6.517.283h-5.724l-16.942 84.715q1.7.283 3.4.284h3.967c18.133.187 33.245-1.604 45.332-5.383c12.087-3.967 20.213-17.754 24.366-41.366q5.1-29.751-10.2-34.283c-10.012-3.02-22.57-4.437-37.682-4.25"/></svg></span><span class="yii-debug-brand-value">{$phpVersion}</span></span><span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">Memory</span><span class="yii-debug-brand-value">{$peakMemory}</span></span><a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="/debug/view?tag=request-1&amp;panel=config" title="Open configuration" aria-label="Open configuration"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37c1 .608 2.296.07 2.572-1.065M9 12a3 3 0 1 0 6 0a3 3 0 0 0-6 0"/></svg></span><span class="yii-debug-brand-label">Config</span></a><button class="yii-debug-brand-chip yii-debug-brand-chip-control yii-debug-brand-chip-copy" type="button" title="Copy debug link" aria-label="Copy debug link" data-yii-debug-copy-link="true"><span class="yii-debug-brand-label" aria-live="polite" data-yii-debug-copy-label="true">Copy link</span></button><button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" title="Switch to light theme" aria-label="Switch to light theme" aria-pressed="true" data-yii-debug-theme-toggle="true" data-current-theme="dark" data-icon-sun="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7&quot;/&gt;&lt;/svg&gt;" data-icon-moon="&lt;svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;1em&quot; height=&quot;1em&quot; viewBox=&quot;0 0 24 24&quot;&gt;&lt;path fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; stroke-width=&quot;2&quot; d=&quot;M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1-8.313-12.454l0 .008&quot;/&gt;&lt;/svg&gt;"><span class="yii-debug-brand-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0-8 0M3 12h1m8-9v1m8 8h1m-9 8v1m-6.4-15.4l.7.7m12.1-.7l-.7.7m0 11.4l.7.7m-12.1-.7l-.7.7"/></svg></span></button>
            </header><div class="yii-debug-layout">
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET https://example.test/?page=2">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="https://example.test/?page=2" data-snapshot-field="url">/?page=2</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">{$requestOneTime}</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax" hidden>AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=auto" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=request-2&amp;panel=auto" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section><nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug?cursor=request-1" title="View request history">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M12 8v4l2 2"/><path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></g></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug/view?tag=request-1&amp;panel=request" title="View Request panel" aria-current="page">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 256 256"><path fill="currentColor" d="M227.32 28.68a16 16 0 0 0-15.66-4.08h-.15L19.57 82.84a16 16 0 0 0-2.49 29.8L102 154l41.3 84.87a15.86 15.86 0 0 0 14.44 9.13q.69 0 1.38-.06a15.88 15.88 0 0 0 14-11.51l58.2-191.94v-.15a16 16 0 0 0-4-15.66m-69.49 203.17l-.05.14v-.07l-40.06-82.3l48-48a8 8 0 0 0-11.31-11.31l-48 48l-82.33-40.06h-.07h.14L216 40Z"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Request
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside><main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            GET /?page=2
            </h1><header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url" title="https://example.test/?page=2">https://example.test/?page=2</span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            <span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">IP</span><span class="yii-debug-request-hero-meta-value">127.0.0.1</span></span><span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">Time</span><span class="yii-debug-request-hero-meta-value">{$requestOneTime}</span></span><span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">Duration</span><span class="yii-debug-request-hero-meta-value">9.0 ms</span></span>
            </div>
            </header><ul class="yii-debug-tabs" role="tablist" aria-label="Request data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="request-tab-0" href="#request-panel-0" role="tab" tabindex="0" aria-controls="request-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Parameters</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="request-tab-1" href="#request-panel-1" role="tab" tabindex="-1" aria-controls="request-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Headers</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="request-tab-2" href="#request-panel-2" role="tab" tabindex="-1" aria-controls="request-panel-2" aria-selected="false" data-yii-debug-toggle="tab">Session</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="request-tab-3" href="#request-panel-3" role="tab" tabindex="-1" aria-controls="request-panel-3" aria-selected="false" data-yii-debug-toggle="tab">Server</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="request-panel-0" role="tabpanel" aria-labelledby="request-tab-0">
            <header class="yii-debug-section-header">
            <h2>
            Routing
            </h2>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <th scope="row">
            Route
            </th><td>
            &#039;home&#039;
            </td>
            </tr><tr>
            <th scope="row">
            Action
            </th><td>
            &#039;App\\\\Web\\\\HomeAction&#039;
            </td>
            </tr><tr>
            <th scope="row">
            Parameters
            </th><td>
            []
            </td>
            </tr>
            </tbody>
            </table>
            </div><header class="yii-debug-section-header">
            <h2>
            Get
            </h2>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <th scope="row">
            page
            </th><td>
            &#039;2&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Post</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Files</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Cookies</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Request Body</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details>
            </div><div class="yii-debug-tab-panel" id="request-panel-1" role="tabpanel" aria-labelledby="request-tab-1" hidden>
            <details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Request Headers</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Response Headers</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details>
            </div><div class="yii-debug-tab-panel" id="request-panel-2" role="tabpanel" aria-labelledby="request-tab-2" hidden>
            <details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Session</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Flashes</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details>
            </div><div class="yii-debug-tab-panel" id="request-panel-3" role="tabpanel" aria-labelledby="request-tab-3" hidden>
            <details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Server</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details>
            </div>
            </div>
            </main>
            </div>
            </div><script src="/debug-assets/yii3-debug/test/dist/js/debug.min.js" type="module"></script></body>
            </html>

            HTML,
            $html,
            'The Request page must match the complete rendered document.',
        );
    }

    public function testReturnNewInstanceWhenSettingConfiguration(): void
    {
        $renderer = $this->rendererWithPanels('page-renderer-new-instance-assets', []);

        self::assertNotSame(
            $renderer,
            $renderer->withExtensionPanels([]),
            'Should return a new instance when setting extension panels, ensuring immutability.',
        );
        self::assertNotSame(
            $renderer,
            $renderer->withRoutePrefix('/developer/debug'),
            'Should return a new instance when setting the route prefix, ensuring immutability.',
        );
    }

    /**
     * @return array{string, string, string}
     */
    private static function expectedConfigFragments(): array
    {
        $summary = (new ConfigDataFactory(['name' => 'Test application']))->create();
        $phpVersion = $summary->php->version;
        [$xdebugVariant, $xdebugState] = self::extensionState($summary->php->xdebug);
        [$apcuVariant, $apcuState] = self::extensionState($summary->php->apcu);
        [$memcacheVariant, $memcacheState] = self::extensionState($summary->php->memcache);
        [$memcachedVariant, $memcachedState] = self::extensionState($summary->php->memcached);
        $packageRows = [];

        foreach ($summary->extensions as $name => $version) {
            $nameParts = explode('/', $name, 2);
            $package = $nameParts[1] ?? $nameParts[0];
            $packageRows[] = <<<HTML
                <div class="yii-debug-package-row">
                <dt class="yii-debug-package-name">
                {$package}
                </dt><dd class="yii-debug-package-version">
                v{$version}
                </dd>
                </div>
                HTML;
        }

        $packageCount = count($packageRows);
        $packageLabel = $packageCount === 1 ? 'package' : 'packages';
        $packageRows = implode('', $packageRows);
        $installedExtensions = $packageCount === 0
            ? ''
            : <<<HTML
                <section class="yii-debug-section">
                <h2 class="yii-debug-section-title">
                <span class="yii-debug-section-mark">::</span> Installed extensions <span class="yii-debug-section-count">{$packageCount}</span>
                </h2><div class="yii-debug-package-groups">
                <article class="yii-debug-package-group">
                <header class="yii-debug-package-group-header">
                <h3 class="yii-debug-package-vendor">
                yiisoft/
                </h3><span class="yii-debug-package-group-count">{$packageCount} {$packageLabel}</span>
                </header><dl class="yii-debug-package-list">
                {$packageRows}
                </dl>
                </article>
                </div>
                </section>
                HTML;

        return [
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">3</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">{$phpVersion}</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value"></span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip yii-debug-readout-chip-muted">debug off</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">Test application</span><span class="yii-debug-readout-meta">instance</span>
            </article>
            </div>
            HTML,
            <<<HTML
            <section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">::</span> PHP extensions
            </h2><div class="yii-debug-ext-strip">
            <span class="yii-debug-ext-pill {$xdebugVariant}"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Xdebug</span><span class="yii-debug-ext-pill-state">{$xdebugState}</span></span><span class="yii-debug-ext-pill {$apcuVariant}"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">APCu</span><span class="yii-debug-ext-pill-state">{$apcuState}</span></span><span class="yii-debug-ext-pill {$memcacheVariant}"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Memcache</span><span class="yii-debug-ext-pill-state">{$memcacheState}</span></span><span class="yii-debug-ext-pill {$memcachedVariant}"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Memcached</span><span class="yii-debug-ext-pill-state">{$memcachedState}</span></span>
            </div>
            </section>
            HTML,
            $installedExtensions,
        ];
    }

    /**
     * @return array{string, string}
     */
    private static function extensionState(bool $enabled): array
    {
        return $enabled ? ['is-on', 'on'] : ['is-off', 'off'];
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaPayload(): array
    {
        return InertiaSnapshot::capture(
            null,
            [
                'component' => 'Site/Index',
                'props' => ['appName' => 'Test application'],
                'url' => '/',
                'version' => 'version-1',
            ],
            ['X-Inertia' => 'true'],
            ['appName'],
            200,
        )->jsonSerialize();
    }

    /**
     * @return array{'request-1': RequestSummary, 'request-2': RequestSummary}
     */
    private function manifest(): array
    {
        return [
            'request-1' => RequestSummary::create('request-1')
                ->withRequest('https://example.test/?page=2', 'GET', '127.0.0.1', 1_725_000_756.0)
                ->withResponse(200)
                ->withProfiling(0.009, 1_145_324),
            'request-2' => RequestSummary::create('request-2')
                ->withRequest('https://example.test/missing', 'POST', '127.0.0.1', 1_725_000_700.0, true)
                ->withResponse(404)
                ->withProfiling(0.015, 2_097_152),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profilingPayload(): array
    {
        return ProfilingSnapshot::captureCompleted(
            2_097_152,
            0.025,
            [
                [
                    'token' => 'Build home response',
                    'category' => 'App\\Web\\HomeAction::__invoke',
                    'context' => [
                        'category' => 'App\\Web\\HomeAction::__invoke',
                        'nestedLevel' => 0,
                        'beginTime' => 1_725_000_756.0,
                        'endTime' => 1_725_000_756.025,
                        'duration' => 0.025,
                        'beginMemory' => 1_048_576,
                        'endMemory' => 1_310_720,
                        'memoryDiff' => 262_144,
                        'trace' => [],
                    ],
                ],
            ],
        )->jsonSerialize();
    }

    private function renderer(): DebugPageRenderer
    {
        return $this->rendererWithPanels(
            'page-renderer-assets',
            [new RequestPanel()],
        );
    }

    private function rendererWithInertia(): DebugPageRenderer
    {
        return $this->rendererWithPanels(
            'page-renderer-inertia-assets',
            [new RequestPanel(), new InertiaPanel()],
        );
    }

    /**
     * @param list<ExtensionPanelInterface> $panels
     */
    private function rendererWithPanels(string $assetDirectory, array $panels): DebugPageRenderer
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-' . $assetDirectory,
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__, 2) . '/vendor',
            ],
        );
        $assetManager = (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(
                (new AssetPublisher($aliases))->withHashCallback(static fn(string $path): string => 'test'),
            );
        $renderer = new DebugPageRenderer(
            new WebView(),
            $assetManager,
            new ConfigDataFactory(['name' => 'Test application']),
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
        );

        return $panels === [] ? $renderer : $renderer->withExtensionPanels($panels);
    }

    private function rendererWithProfiling(): DebugPageRenderer
    {
        return $this->rendererWithPanels(
            'page-renderer-profiling-assets',
            [new RequestPanel(), new ProfilingPanel()],
        );
    }

    private function rendererWithVite(): DebugPageRenderer
    {
        return $this->rendererWithPanels(
            'page-renderer-vite-assets',
            [new RequestPanel(), new VitePanel()],
        );
    }

    /**
     * @param Closure(): string $render
     *
     * @return array{string, string}
     */
    private static function renderWithPeakMemory(Closure $render): array
    {
        $html = $render();
        $matched = preg_match(
            '~<span class="yii-debug-brand-chip yii-debug-brand-chip-mem">'
                . '<span class="yii-debug-brand-label">Memory</span>'
                . '<span class="yii-debug-brand-value">(?<memory>[0-9]+\.[0-9]{2} MB)</span></span>~',
            $html,
            $matches,
        );

        if ($matched !== 1) {
            throw new RuntimeException('Unable to read peak memory from the rendered debugger page.');
        }

        return [$matches['memory'], $html];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(): array
    {
        return RequestSnapshot::capture(
            [
                'action' => 'App\\Web\\HomeAction',
                'actionParams' => [],
                'flashes' => [],
                'general' => ['method' => 'GET'],
                'requestBody' => [],
                'requestHeaders' => [],
                'responseHeaders' => [],
                'route' => 'home',
                'statusCode' => 200,
                'COOKIE' => [],
                'FILES' => [],
                'GET' => ['page' => '2'],
                'POST' => [],
                'SERVER' => [],
                'SESSION' => [],
            ],
        )->jsonSerialize();
    }

    /**
     * @return array<string, mixed>
     */
    private function vitePayload(): array
    {
        $viteSnapshot = new ViteSnapshot(
            [
                new ViteComponent(
                    id: 'vite',
                    class: Vite::class,
                    implementation: ViteComponent::IMPLEMENTATION_MODERN,
                    inspectionAvailable: true,
                    mode: ViteComponent::MODE_DEVELOPMENT,
                    entrypoints: ['resources/js/app.ts'],
                    baseUrl: '',
                    devServerUrl: 'http://127.0.0.1:5173',
                    manifestPath: '',
                    includeViteClient: true,
                    modulePreload: null,
                    chunks: [],
                ),
            ],
        );

        return $viteSnapshot->jsonSerialize();
    }
}
