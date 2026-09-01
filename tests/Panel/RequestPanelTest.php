<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\RequestPanel;

/**
 * Unit tests for the built-in Request panel presentation and toolbar metric.
 */
final class RequestPanelTest extends TestCase
{
    public function testMalformedPayloadRetainsTheNativeHydrationFailure(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot',
        );

        (new RequestPanel())->render(['statusCode' => '200']);
    }

    public function testMetadataAndVisibilityMatchTheBuiltInRequestPanel(): void
    {
        $panel = new RequestPanel();

        self::assertSame(
            'request',
            $panel->id(),
            "Stable panel ID must be 'request'.",
        );
        self::assertSame(
            'Request',
            $panel->name(),
            "Panel name must be 'Request'.",
        );
        self::assertSame(
            'request',
            $panel->icon(),
            "Panel icon must be 'request'.",
        );
        self::assertFalse(
            $panel->hasContent([]),
            'An absent capture must stay hidden.',
        );
        self::assertTrue(
            $panel->hasContent($this->payload()),
            'A captured request must be visible in primary navigation.',
        );
    }

    public function testRenderEscapesCapturedValues(): void
    {
        $payload = $this->payload();

        $data = RequestSnapshot::fromArray($payload, '$.panels.request')
            ->data();

        $data['GET'] = ['query' => '<script>alert(1)</script>'];

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertSame(
            <<<'HTML'
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url"></span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            <span class="yii-debug-snapshot-tag">HTTPS</span>
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
            &#039;orders/view&#039;
            </td>
            </tr><tr>
            <th scope="row">
            Action
            </th><td>
            &#039;App\\Web\\OrderAction&#039;
            </td>
            </tr><tr>
            <th scope="row">
            Parameters
            </th><td>
            [
                &#039;id&#039; =&gt; &#039;7&#039;
            ]
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
            query
            </th><td>
            &#039;&lt;script&gt;alert(1)&lt;/script&gt;&#039;
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
            <header class="yii-debug-section-header">
            <h2>
            Request Headers
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Request Headers" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
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
            Accept
            </th><td>
            &#039;text/html&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div><header class="yii-debug-section-header">
            <h2>
            Response Headers
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Response Headers" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
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
            Content-Type
            </th><td>
            &#039;text/html; charset=UTF-8&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
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
            <header class="yii-debug-section-header">
            <h2>
            Server
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Server" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
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
            REMOTE_ADDR
            </th><td>
            &#039;127.0.0.1&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Captured request values must be escaped throughout the complete panel markup.',
        );
    }

    public function testRenderWithSummaryMatchesTheSharedYiiRequestLayout(): void
    {
        $summary = RequestSummary::create('request-1')
            ->withRequest(
                'https://example.test/orders?page=2',
                'GET',
                '127.0.0.1',
                0.0,
            )
            ->withResponse(200)
            ->withProfiling(0.009, 1_145_324);

        $html = (new RequestPanel())
            ->renderWithSummary($this->payload(), $summary);

        self::assertSame(
            <<<'HTML'
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url" title="https://example.test/orders?page=2">https://example.test/orders?page=2</span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            <span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">IP</span><span class="yii-debug-request-hero-meta-value">127.0.0.1</span></span><span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">Duration</span><span class="yii-debug-request-hero-meta-value">9.0 ms</span></span><span class="yii-debug-snapshot-tag">HTTPS</span>
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
            &#039;orders/view&#039;
            </td>
            </tr><tr>
            <th scope="row">
            Action
            </th><td>
            &#039;App\\Web\\OrderAction&#039;
            </td>
            </tr><tr>
            <th scope="row">
            Parameters
            </th><td>
            [
                &#039;id&#039; =&gt; &#039;7&#039;
            ]
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
            <header class="yii-debug-section-header">
            <h2>
            Request Headers
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Request Headers" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
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
            Accept
            </th><td>
            &#039;text/html&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div><header class="yii-debug-section-header">
            <h2>
            Response Headers
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Response Headers" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
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
            Content-Type
            </th><td>
            &#039;text/html; charset=UTF-8&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
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
            <header class="yii-debug-section-header">
            <h2>
            Server
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Server" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
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
            REMOTE_ADDR
            </th><td>
            &#039;127.0.0.1&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Request detail must match the complete shared Yii panel markup.',
        );
    }

    public function testToolbarItemsExposeKnownAndUnknownStatusCodes(): void
    {
        $panel = new RequestPanel();

        $notFound = $panel
            ->toolbarItems(RequestSnapshot::capture(['statusCode' => 404])
            ->jsonSerialize());
        $unknown = $panel
            ->toolbarItems(RequestSnapshot::capture(['statusCode' => 599])
            ->jsonSerialize());

        self::assertCount(
            1,
            $notFound,
            'Request panel must expose exactly one toolbar metric.',
        );
        self::assertCount(
            1,
            $unknown,
            'Unknown status codes must still expose one toolbar metric.',
        );
        self::assertSame(
            '404',
            $notFound[0]->value,
            'Toolbar metric must expose the response code.',
        );
        self::assertSame(
            'status-4xx',
            $notFound[0]->status,
            'Toolbar metric must use the 4xx status token.',
        );
        self::assertSame(
            'Status code: 404 Not Found',
            $notFound[0]->title,
            'Known status tooltip must include its reason phrase.',
        );
        self::assertSame(
            'Status code: 599',
            $unknown[0]->title,
            'Unknown status tooltip must omit a reason phrase cleanly.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return RequestSnapshot::capture(
            [
                'action' => 'App\\Web\\OrderAction',
                'actionParams' => ['id' => '7'],
                'flashes' => [],
                'general' => [
                    'isAjax' => false,
                    'isFlash' => false,
                    'isPjax' => false,
                    'isSecureConnection' => true,
                    'method' => 'GET',
                ],
                'requestBody' => [],
                'requestHeaders' => ['Accept' => 'text/html'],
                'responseHeaders' => ['Content-Type' => 'text/html; charset=UTF-8'],
                'route' => 'orders/view',
                'statusCode' => 200,
                'COOKIE' => [],
                'FILES' => [],
                'GET' => ['page' => '2'],
                'POST' => [],
                'SERVER' => ['REMOTE_ADDR' => '127.0.0.1'],
                'SESSION' => [],
            ],
        )->jsonSerialize();
    }
}
