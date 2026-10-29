<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use PHPForge\Debug\Storage\{ExceptionSnapshot, PanelFailure};

/**
 * Data provider for {@see \Yii3\Debug\Tests\Web\DebugPageRendererTest} test cases.
 */
final class DebugPageRendererProvider
{
    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function extensionLinks(): iterable
    {
        yield 'configuration page' => [
            null,
            <<<HTML
            <nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Extensions debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=alpha" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            alpha
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=Beta" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Beta
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=gamma" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            gamma
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=zeta" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            zeta
            </span>
            </a>
            </li>
            </ul>
            </nav>
            HTML,
        ];
        yield 'raw capture page' => [
            'zeta',
            <<<HTML
            <nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Extensions debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=alpha" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            alpha
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=Beta" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Beta
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?tag=request-1&amp;panel=gamma" title="View captured data without an installed presenter">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            gamma
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug/view?tag=request-1&amp;panel=zeta" title="View captured data without an installed presenter" aria-current="page">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            zeta
            </span>
            </a>
            </li>
            </ul>
            </nav>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{string, array<string, array<string, mixed>>, array<string, PanelFailure>, string}>
     */
    public static function jsonSnapshots(): iterable
    {
        yield 'capture failure' => [
            'cache',
            [],
            [
                'cache' => new PanelFailure(
                    PanelFailure::CAPTURE,
                    new ExceptionSnapshot(
                        'RuntimeException',
                        'Cache capture failed.',
                        0,
                        '/app/src/CacheCollector.php',
                        42,
                        [],
                        'RuntimeException: Cache capture failed. in /app/src/CacheCollector.php:42',
                        null,
                    ),
                ),
            ],
            <<<HTML
            <main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            GET /?page=2
            </h1><header class="yii-debug-panel-heading">
            <div class="yii-debug-panel-heading-copy">
            <small class="yii-debug-panel-heading-eyebrow">Selected panel</small><h2 id="yii-debug-panel-title">
            cache
            </h2>
            </div><span class="yii-debug-panel-heading-kind">JSON snapshot</span>
            </header><div class="yii-debug-callout yii-debug-callout-danger" role="alert">
            <div>
            <strong>Panel capture failed.</strong><br><span>RuntimeException: Cache capture failed. in /app/src/CacheCollector.php:42</span>
            </div>
            </div><pre class="yii-debug-panel-payload" tabindex="0">
            <code>[]</code>
            </pre>
            </main>
            HTML,
        ];
        yield 'raw payload with unescaped characters' => [
            'custom',
            ['custom' => ['url' => 'https://example.test/café', 'ids' => [1]]],
            [],
            <<<HTML
            <main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            GET /?page=2
            </h1><header class="yii-debug-panel-heading">
            <div class="yii-debug-panel-heading-copy">
            <small class="yii-debug-panel-heading-eyebrow">Selected panel</small><h2 id="yii-debug-panel-title">
            custom
            </h2>
            </div><span class="yii-debug-panel-heading-kind">JSON snapshot</span>
            </header><pre class="yii-debug-panel-payload" tabindex="0">
            <code>{
                "url": "https://example.test/café",
                "ids": [
                    1
                ]
            }</code>
            </pre>
            </main>
            HTML,
        ];
        yield 'registered panel without capture' => [
            'request',
            [],
            [],
            <<<HTML
            <main class="yii-debug-main yii-debug-card" id="yii-debug-main" tabindex="-1">
            <h1 class="yii-debug-sr-only">
            GET /?page=2
            </h1><header class="yii-debug-panel-heading">
            <div class="yii-debug-panel-heading-copy">
            <small class="yii-debug-panel-heading-eyebrow">Selected panel</small><h2 id="yii-debug-panel-title">
            Request
            </h2>
            </div><span class="yii-debug-panel-heading-kind">JSON snapshot</span>
            </header><pre class="yii-debug-panel-payload" tabindex="0">
            <code>[]</code>
            </pre>
            </main>
            HTML,
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function snapshotNavigators(): iterable
    {
        yield 'middle capture' => [
            'third',
            <<<HTML
            <div class="yii-debug-request-nav-row" role="group">
            <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=first&amp;panel=config" title="Newest request" aria-label="Newest captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg>
            </a>
            <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=second&amp;panel=config" title="Newer request" aria-label="Newer captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg>
            </a>
            <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=fourth&amp;panel=config" title="Older request" aria-label="Older captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg>
            </a>
            <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=fourth&amp;panel=config" title="Oldest request" aria-label="Oldest captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg>
            </a>
            </div>
            HTML,
        ];
        yield 'oldest capture' => [
            'fourth',
            <<<HTML
            <div class="yii-debug-request-nav-row" role="group">
            <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=first&amp;panel=config" title="Newest request" aria-label="Newest captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg>
            </a>
            <a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=third&amp;panel=config" title="Newer request" aria-label="Newer captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg>
            </a>
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Older request" disabled aria-label="Older captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg>
            </button>
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Oldest request" disabled aria-label="Oldest captured request">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg>
            </button>
            </div>
            HTML,
        ];
    }
}
