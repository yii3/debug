<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\InertiaPanel;

/**
 * Unit tests for the stateless Inertia extension-panel presentation.
 */
final class InertiaPanelTest extends TestCase
{
    private InertiaPanel $panel;

    public function testCapturedPageHeadersPropsAndRawPayloadAreEscaped(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => '<script>alert("component")</script>',
                    'props' => [
                        '<img src=x onerror=alert(1)>' => '</textarea><script>alert("value")</script>',
                    ],
                    'url' => '/?<svg onload=alert(1)>',
                    'version' => '<b>v1</b>',
                ],
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Data' => '<iframe src=javascript:alert(1)>',
                ],
            ),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>&lt;script&gt;alert("component")&lt;/script&gt;</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Partial reload</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>1</strong> prop</span>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono">
            <tbody>
            <tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Component
            </th><td>
            &lt;script&gt;alert("component")&lt;/script&gt;
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            URL
            </th><td>
            /?&lt;svg onload=alert(1)&gt;
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Version
            </th><td>
            &lt;b&gt;v1&lt;/b&gt;
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Visit
            </th><td>
            Partial reload
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Status
            </th><td>
            200
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            X-Inertia-Partial-Data
            </th><td>
            &lt;iframe src=javascript:alert(1)&gt;
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
            <strong>&lt;img src=x onerror=alert(1)&gt;</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            string(42)
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            "&lt;/textarea&gt;&lt;script&gt;alert(\"value\")&lt;/script&gt;"
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
                "component": "&lt;script&gt;alert(\"component\")&lt;/script&gt;",
                "props": {
                    "&lt;img src=x onerror=alert(1)&gt;": "&lt;/textarea&gt;&lt;script&gt;alert(\"value\")&lt;/script&gt;"
                },
                "url": "/?&lt;svg onload=alert(1)&gt;",
                "version": "&lt;b&gt;v1&lt;/b&gt;"
            }
            </pre>
            </div>
            </details>
            HTML,
            $html,
            'Captured values must be escaped throughout the complete Inertia panel markup.',
        );
    }

    public function testContractMetadataAndPerCaptureVisibility(): void
    {
        self::assertSame(
            'inertia',
            $this->panel->id(),
            'Panel ID must match the persisted payload key.',
        );
        self::assertSame(
            'Inertia',
            $this->panel->name(),
            'Panel name must match the sidebar label.',
        );
        self::assertSame(
            'inertia',
            $this->panel->icon(),
            'Panel icon must use the shared Inertia glyph.',
        );
        self::assertFalse(
            $this->panel->hasContent($this->payload(null)),
            'A plain capture without a page or Inertia request header must stay out of the sidebar.',
        );
        self::assertTrue(
            $this->panel->hasContent(
                $this->payload(['component' => 'Site/Index', 'props' => [], 'url' => '/', 'version' => 'v1']),
            ),
            'A captured page must expose the panel.',
        );
        self::assertTrue(
            $this->panel->hasContent($this->payload(null, ['X-Inertia' => 'true'])),
            'An Inertia XHR without a page must still expose the panel.',
        );
    }

    public function testFullPageLoadRendersSummaryInformationPropsAndRawPayload(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => 'Site/Index',
                    'props' => ['user' => ['id' => 1]],
                    'url' => '/dashboard',
                    'version' => 42,
                ],
                statusCode: 201,
            ),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>Site/Index</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Full page load</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>1</strong> prop</span>
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
            /dashboard
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Version
            </th><td>
            42
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Visit
            </th><td>
            Full page load
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Status
            </th><td>
            201
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
            <strong>user</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            array(1)
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            {"id":1}
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
                    "user": {
                        "id": 1
                    }
                },
                "url": "/dashboard",
                "version": 42
            }
            </pre>
            </div>
            </details>
            HTML,
            $html,
            'Full page load must match the complete Inertia panel markup.',
        );
    }

    public function testMissingPageAndEmptyPropsRenderDistinctEmptyStates(): void
    {
        $missingPage = $this->panel->render($this->payload(null, ['X-Inertia' => 'true']));
        $emptyProps = $this->panel->render(
            $this->payload(['component' => 'Site/Index', 'props' => [], 'url' => '/', 'version' => 'v1']),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>—</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Inertia visit</span>
            </header><div class="yii-debug-empty-state">
            <h2>
            No Inertia page in this request
            </h2><p>
            This response was not produced by <code>Inertia::render()</code>, so there is no page object to inspect.
            </p><p>
            Both full page loads and Inertia XHR visits populate this view; plain JSON endpoints, redirects, and asset requests do not.
            </p>
            </div>
            HTML,
            $missingPage,
            'An Inertia request without a page must match the complete shared empty state.',
        );
        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>Site/Index</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Full page load</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>0</strong> props</span>
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
            v1
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Visit
            </th><td>
            Full page load
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
            </h2><p>
            The page rendered without props.
            </p><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Raw payload</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <pre>
            {
                "component": "Site/Index",
                "props": [],
                "url": "/",
                "version": "v1"
            }
            </pre>
            </div>
            </details>
            HTML,
            $emptyProps,
            'An Inertia page without props must match the complete panel markup.',
        );
    }

    public function testPartialReloadRendersVisitAndNegotiationHeaders(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => 'Users/Index',
                    'props' => ['users' => [['id' => 7]]],
                    'url' => '/users',
                    'version' => 'v2',
                ],
                [
                    'X-Inertia' => 'true',
                    'X-Inertia-Partial-Component' => 'Users/Index',
                    'X-Inertia-Partial-Data' => 'users',
                    'X-Inertia-Version' => 'v2',
                ],
            ),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>Users/Index</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Partial reload</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>1</strong> prop</span>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono">
            <tbody>
            <tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Component
            </th><td>
            Users/Index
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            URL
            </th><td>
            /users
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Version
            </th><td>
            v2
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Visit
            </th><td>
            Partial reload
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Status
            </th><td>
            200
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            X-Inertia-Partial-Component
            </th><td>
            Users/Index
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            X-Inertia-Partial-Data
            </th><td>
            users
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            X-Inertia-Version
            </th><td>
            v2
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
            <strong>users</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            array(1)
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            [{"id":7}]
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
                "component": "Users/Index",
                "props": {
                    "users": [
                        {
                            "id": 7
                        }
                    ]
                },
                "url": "/users",
                "version": "v2"
            }
            </pre>
            </div>
            </details>
            HTML,
            $html,
            'Partial reload must match the complete Inertia panel markup.',
        );
    }

    public function testPropsExposeSharedAndPageOriginsAndScalarTypes(): void
    {
        $html = $this->panel->render(
            $this->payload(
                [
                    'component' => 'Site/Index',
                    'props' => [
                        'auth' => ['isGuest' => true],
                        'title' => 'Welcome',
                        'count' => 7,
                        'ratio' => 1.5,
                        'enabled' => true,
                        'missing' => null,
                    ],
                    'url' => '/',
                    'version' => 'v1',
                ],
                sharedKeys: ['auth'],
            ),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>Site/Index</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Full page load</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>6</strong> props</span>
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
            v1
            </td>
            </tr><tr>
            <th style='max-width: none; overflow-wrap: normal; white-space: nowrap;' scope="row">
            Visit
            </th><td>
            Full page load
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
            <strong>auth</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-info">shared</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            array(1)
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            {"isGuest":true}
            </td>
            </tr><tr>
            <td>
            2
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            <strong>title</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            string(7)
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            "Welcome"
            </td>
            </tr><tr>
            <td>
            3
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            <strong>count</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            int
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            7
            </td>
            </tr><tr>
            <td>
            4
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            <strong>ratio</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            float
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            1.5
            </td>
            </tr><tr>
            <td>
            5
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            <strong>enabled</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            bool
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            true
            </td>
            </tr><tr>
            <td>
            6
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            <strong>missing</strong>
            </td><td class="yii-debug-cell-pill">
            <span class="yii-debug-badge yii-debug-badge-muted">page</span>
            </td><td class="yii-debug-cell-mono yii-debug-cell-nowrap">
            null
            </td><td class="yii-debug-cell-mono yii-debug-cell-payload">
            null
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
                    "auth": {
                        "isGuest": true
                    },
                    "title": "Welcome",
                    "count": 7,
                    "ratio": 1.5,
                    "enabled": true,
                    "missing": null
                },
                "url": "/",
                "version": "v1"
            }
            </pre>
            </div>
            </details>
            HTML,
            $html,
            'Prop origins and scalar types must match the complete Inertia panel markup.',
        );
    }

    public function testToolbarItemsExposeTheCapturedComponent(): void
    {
        $items = $this->panel->toolbarItems(
            $this->payload(
                [
                    'component' => 'Site/Index',
                    'props' => [],
                    'url' => '/',
                    'version' => 'v1',
                ],
            ),
        );

        self::assertCount(
            1,
            $items,
            'A captured component must expose one toolbar metric.',
        );
        self::assertSame(
            [
                'value' => 'Site/Index',
                'status' => 'default',
                'title' => 'Inertia component',
            ],
            $items[0]->jsonSerialize(),
            'The toolbar metric must match the Yii2 Inertia component chip.',
        );
    }

    public function testToolbarItemsStayEmptyWithoutACapturedComponent(): void
    {
        foreach (
            [
                $this->payload(null, ['X-Inertia' => 'true']),
                $this->payload(['component' => '', 'props' => [], 'url' => '/', 'version' => 'v1']),
                $this->payload(['props' => [], 'url' => '/', 'version' => 'v1']),
            ] as $payload
        ) {
            self::assertSame(
                [],
                $this->panel->toolbarItems($payload),
                'A capture without a component must stay out of the toolbar.',
            );
        }
    }

    public function testVersionConflictRendersReloadExplanationAndEscapedLocation(): void
    {
        $html = $this->panel->render(
            $this->payload(
                null,
                ['X-Inertia' => 'true', 'X-Inertia-Version' => 'stale'],
                statusCode: 409,
                location: 'https://example.test/<script>alert(1)</script>',
            ),
        );

        self::assertSame(
            <<<'HTML'
            <h1 class="yii-debug-sr-only">
            Inertia
            </h1><header class="yii-debug-grid-summary">
            <span><strong>—</strong></span><span class="yii-debug-grid-summary-sep">·</span><span>Version conflict</span>
            </header><div class="yii-debug-empty-state">
            <h2>
            Version conflict interrupted this visit
            </h2><p>
            The client asset version sent in <code>X-Inertia-Version</code> no longer matches the server version, so Inertia answered <code>409</code> and asked the client to reload the full page.
            </p><p>
            Reload target: <code>https://example.test/&lt;script&gt;alert(1)&lt;/script&gt;</code>
            </p>
            </div>
            HTML,
            $html,
            'Version conflict must match the complete escaped Inertia empty state.',
        );
    }

    protected function setUp(): void
    {
        $this->panel = new InertiaPanel();
    }

    /**
     * @param array<array-key, mixed>|null $page
     * @param array<array-key, mixed> $requestHeaders
     * @param array<array-key, mixed> $sharedKeys
     *
     * @return array<string, mixed>
     */
    private function payload(
        array|null $page,
        array $requestHeaders = [],
        array $sharedKeys = [],
        int $statusCode = 200,
        string|null $location = null,
    ): array {
        return InertiaSnapshot::capture(
            $location,
            $page,
            $requestHeaders,
            $sharedKeys,
            $statusCode,
        )->jsonSerialize();
    }
}
