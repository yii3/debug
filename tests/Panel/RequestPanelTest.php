<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Storage\{HydrationException, RequestSummary};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Panel\RequestPanel;
use Yiisoft\Router\{Route, RouteCollection, RouteCollectionInterface, RouteCollector};

use function strpos;

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

        (new RequestPanel())
            ->render(['statusCode' => '200']);
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

    public function testRenderEscapesCapturedInputAndRouteMetadata(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['GET'] = ['query' => '<script>alert(1)</script>'];
        $data['action'] = 'App\\Web\\<OrderAction>';
        $data['routeDefinition'] = [
            'name' => 'orders/view',
            'pattern' => '/orders/<img src=x onerror=alert(1)>',
            'methods' => ['GET'],
            'hosts' => ['<api.example.test>'],
            'action' => 'App\\Web\\<OrderAction>',
            'middlewares' => ['App\\Middleware\\<Authentication>'],
        ];

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)->jsonSerialize());

        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Captured input values must be HTML escaped.',
        );
        self::assertStringContainsString(
            '/orders/&lt;img src=x onerror=alert(1)&gt;',
            $html,
            'Captured route patterns must be HTML escaped.',
        );
        self::assertStringContainsString(
            '&lt;api.example.test&gt;',
            $html,
            'Captured host restrictions must be HTML escaped.',
        );
        self::assertStringContainsString(
            'App\\Web\\&lt;OrderAction&gt;',
            $html,
            'Captured action descriptors must be HTML escaped.',
        );
        self::assertStringContainsString(
            'App\\Middleware\\&lt;Authentication&gt;',
            $html,
            'Captured middleware descriptors must be HTML escaped.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Captured values must not inject executable markup.',
        );
        self::assertStringNotContainsString(
            '<img ',
            $html,
            'Route metadata must not inject executable markup.',
        );
    }

    public function testRenderLegacySnapshotUsesCanonicalOverviewWithoutInventingAnInventory(): void
    {
        $html = (new RequestPanel())
            ->render($this->payload());

        self::assertStringContainsString(
            'yii-debug-request-overview',
            $html,
            'Snapshots created before route definitions existed must use the shared request overview.',
        );
        self::assertStringContainsString(
            'orders/view',
            $html,
            'The overview must fall back to the legacy resolved route field.',
        );
        self::assertStringContainsString(
            'App\\Web\\OrderAction',
            $html,
            'The overview must fall back to the legacy action field.',
        );
        self::assertStringContainsString(
            '>Input</a>',
            $html,
            'Legacy snapshots must use the canonical Input tab.',
        );
        self::assertStringNotContainsString(
            "<h2>\nRouting\n</h2>",
            $html,
            'Legacy route fields must not reintroduce the duplicate Routing table.',
        );
        self::assertStringNotContainsString(
            '>Routes (',
            $html,
            'A live inventory must not be fabricated when no collection is injected.',
        );
    }

    public function testRenderListsFiltersAndMarksTheMatchedCurrentApplicationRoute(): void
    {
        $route = Route::methods(['GET', 'HEAD'], '/orders/<script>')
            ->name('orders/view')
            ->hosts('api.example.test', 'www.example.test')
            ->middleware('App\\Middleware\\Authentication')
            ->action('App\\Web\\<OrderAction>');
        $secondRoute = Route::post('/health')
            ->name('health')
            ->action('App\\Web\\HealthAction');

        $routes = new RouteCollection(
            (new RouteCollector())
                ->addRoute($route)
                ->addRoute($secondRoute),
        );

        $html = (new RequestPanel($routes))
            ->render($this->payload());

        self::assertStringContainsString(
            '>Routes (2)</a>',
            $html,
            'The tab must expose the inventory count.',
        );
        self::assertStringContainsString(
            'class="yii-debug-route-inventory-provenance"',
            $html,
            'The route inventory must identify the source and lifetime of its data.',
        );
        self::assertStringContainsString(
            'Source: Current application configuration. Live configuration may differ from this capture.',
            $html,
            'The route inventory must distinguish live configuration from captured request data.',
        );
        self::assertStringContainsString(
            'aria-label="Filter routes" data-yii-debug-filter="true"',
            $html,
            'Registered routes must remain searchable through the shared filter contract.',
        );
        self::assertStringContainsString(
            'class="yii-debug-route-ledger"',
            $html,
            'Registered routes must use the shared disclosure ledger.',
        );
        self::assertStringContainsString(
            'data-yii-debug-filter-target="true"',
            $html,
            'The route table wrapper must expose a filter target.',
        );
        self::assertStringContainsString(
            'class="yii-debug-row-success"',
            $html,
            'The route matching the selected request must be visually distinguished.',
        );
        self::assertStringContainsString(
            'class="yii-debug-badge yii-debug-badge-success yii-debug-route-match">Matched</span>',
            $html,
            'The matched row must include a visible non-color-only marker.',
        );
        self::assertStringContainsString(
            '/orders/&lt;script&gt;',
            $html,
            'Live route patterns must be HTML escaped.',
        );
        self::assertStringContainsString(
            'App\\Web\\&lt;OrderAction&gt;',
            $html,
            'Live route action descriptors must be HTML escaped.',
        );
        self::assertStringContainsString(
            'App\\Web\\HealthAction',
            $html,
            'Every route in the current collection must be rendered.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Live definitions must not inject executable markup.',
        );

        $input = strpos($html, '>Input</a>');
        $headers = strpos($html, '>Headers</a>');
        $session = strpos($html, '>Session</a>');
        $inventory = strpos($html, '>Routes (2)</a>');
        $server = strpos($html, '>Server</a>');

        self::assertNotFalse(
            $input,
            'Input must be present.',
        );
        self::assertNotFalse(
            $headers,
            'Headers must be present.',
        );
        self::assertNotFalse(
            $session,
            'Session must be present for a captured session bucket.',
        );
        self::assertNotFalse(
            $inventory,
            'Routes must be present when the collection is available.',
        );
        self::assertNotFalse(
            $server,
            'Server must be present when server data was captured.',
        );
        self::assertTrue(
            $input < $headers && $headers < $session && $session < $inventory && $inventory < $server,
            'Canonical Request tabs must render as Input, Headers, Session, Routes, then Server.',
        );
    }

    public function testRenderOmitsTheOptionalSessionTabWhenNoSessionWasCaptured(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        unset($data['SESSION'], $data['flashes']);

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringNotContainsString(
            '>Session</a>',
            $html,
            'Request must not claim that a session was active when no session buckets were captured.',
        );
        self::assertMatchesRegularExpression(
            '~>Input</a>.*>Headers</a>.*>Server</a>~s',
            $html,
            'Removing Session must retain the remaining canonical tab order.',
        );
    }

    public function testRenderPresentsCapturedRouteExecutionInThePersistentOverview(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['routeDefinition'] = [
            'name' => 'orders/view',
            'pattern' => '/orders/{id}',
            'methods' => ['GET', 'HEAD'],
            'hosts' => ['api.example.test'],
            'action' => 'App\\Web\\OrderAction',
            'middlewares' => ['App\\Middleware\\Authentication', 'Closure'],
        ];

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringContainsString(
            'class="yii-debug-request-overview yii-debug-verb-get"',
            $html,
            'Request and routing identity must share the persistent execution overview.',
        );
        self::assertStringContainsString(
            'yii-debug-request-overview-metrics',
            $html,
            'Route and action metrics must remain visible independently of the selected tab.',
        );
        self::assertStringContainsString(
            'orders/view',
            $html,
            'The overview must identify the resolved route.',
        );
        self::assertStringContainsString(
            '/orders/{id}',
            $html,
            'The overview must retain the matched pattern.',
        );
        self::assertStringContainsString(
            'GET',
            $html,
            'The overview must retain the accepted methods.',
        );
        self::assertStringContainsString(
            'HEAD',
            $html,
            'Every accepted method must remain visible.',
        );
        self::assertStringContainsString(
            'api.example.test',
            $html,
            'The overview must retain host restrictions.',
        );
        self::assertStringContainsString(
            'App\\Web\\OrderAction',
            $html,
            'The overview must retain the dispatched action.',
        );
        self::assertStringContainsString(
            'App\\Middleware\\Authentication',
            $html,
            'The overview must retain the effective middleware descriptors.',
        );
        self::assertStringContainsString(
            'id',
            $html,
            'The overview must retain matched parameter names.',
        );
        self::assertStringContainsString(
            '7',
            $html,
            'The overview must retain matched parameter values.',
        );
        self::assertStringContainsString(
            '>Input</a>',
            $html,
            'Request input must remain the primary tab.',
        );
        self::assertStringNotContainsString(
            '>Current Route</a>',
            $html,
            'The route execution summary must not be isolated in a competing tab.',
        );
        self::assertStringNotContainsString(
            "<h2>\nRouting\n</h2>",
            $html,
            'Route and action data must not be repeated in a legacy Routing table.',
        );
        self::assertStringNotContainsString(
            '>Routes (',
            $html,
            'A route inventory tab must not be fabricated when no collection is injected.',
        );
    }

    public function testRenderRetainsAResolvedRouteWhenNoActionWasCaptured(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['action'] = null;
        $data['route'] = 'health';
        $data['routeDefinition'] = null;

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringContainsString(
            'health',
            $html,
            'A resolved route must remain useful when its action descriptor is unavailable.',
        );
        self::assertStringContainsString(
            'yii-debug-request-overview',
            $html,
            'An unavailable action must not remove the route execution overview.',
        );
    }

    public function testRenderSurfacesCurrentRouteInventoryFailuresWithoutLosingCapturedData(): void
    {
        $routes = self::createStub(RouteCollectionInterface::class);

        $routes
            ->method('getRoutes')
            ->willThrowException(new RuntimeException('Unable to load <routes>.'));

        $html = (new RequestPanel($routes))
            ->render($this->payload());

        self::assertStringContainsString(
            'yii-debug-route-inventory-error',
            $html,
            'A live route collection failure must use the explicit inventory error treatment.',
        );
        self::assertStringContainsString(
            'Current route configuration could not be read: RuntimeException: Unable to load &lt;routes&gt;.',
            $html,
            'A live route collection failure must be escaped and surfaced.',
        );
        self::assertStringContainsString(
            'yii-debug-request-overview',
            $html,
            'A live inventory failure must not remove captured route execution data.',
        );
        self::assertStringContainsString(
            '>Input</a>',
            $html,
            'A live inventory failure must not remove the shared Request input.',
        );
    }

    public function testRenderSurfacesMalformedCapturedRouteMetadataWithoutLosingRequestData(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['routeDefinition'] = ['name' => 'orders/view'];

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringContainsString(
            'class="yii-debug-callout yii-debug-callout-danger yii-debug-request-routing-error"',
            $html,
            'Malformed captured route metadata must produce an explicit diagnostic callout.',
        );
        self::assertStringContainsString(
            "Captured route metadata could not be read: Route definition key 'pattern' must be a string.",
            $html,
            'The route diagnostic must explain which captured field is malformed.',
        );
        self::assertStringContainsString(
            'orders/view',
            $html,
            'Malformed metadata must retain the canonical route fallback.',
        );
        self::assertStringContainsString(
            '>Headers</a>',
            $html,
            'Malformed route metadata must not prevent the rest of Request from rendering.',
        );
    }

    public function testRenderSurfacesNonArrayCapturedRouteMetadata(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['action'] = null;
        $data['route'] = '';
        $data['routeDefinition'] = '<invalid>';

        $html = (new RequestPanel())->render(RequestSnapshot::capture($data)->jsonSerialize());

        self::assertStringContainsString(
            'yii-debug-request-routing-error',
            $html,
            'A non-array route definition must produce an explicit diagnostic callout.',
        );
        self::assertStringContainsString(
            'Captured route metadata must be an array or null.',
            $html,
            'The malformed definition must be diagnosed without exposing its value.',
        );
        self::assertStringNotContainsString(
            '<invalid>',
            $html,
            'Malformed metadata values must not inject markup into the diagnostic.',
        );
    }

    public function testRenderUsesCapturedDefinitionFallbacksForEmptyCanonicalRouteSlots(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['action'] = '';
        $data['route'] = '';
        $data['routeDefinition'] = [
            'name' => 'orders/fallback',
            'pattern' => '/orders/{id}',
            'methods' => ['GET'],
            'hosts' => [],
            'action' => 'App\\Web\\FallbackAction',
            'middlewares' => null,
        ];

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringContainsString(
            'orders/fallback',
            $html,
            'The persisted route definition must restore an unavailable canonical route name.',
        );
        self::assertStringContainsString(
            'App\\Web\\FallbackAction',
            $html,
            'The persisted route definition must restore an unavailable canonical action.',
        );
        self::assertStringContainsString(
            'Unavailable',
            $html,
            'Missing collection metadata must not be misreported as an empty middleware stack.',
        );
    }

    public function testRenderUsesDedicatedEmptyStatesForAnUnmatchedRouteAndEmptyCollection(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['action'] = null;
        $data['actionParams'] = [];
        $data['route'] = '';
        $data['routeDefinition'] = null;

        $html = (new RequestPanel(new RouteCollection(new RouteCollector())))
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringContainsString(
            'yii-debug-request-overview',
            $html,
            'An unmatched request must retain the request execution overview.',
        );
        self::assertStringContainsString(
            '>Routes (0)</a>',
            $html,
            'An empty available collection must retain a zero-count Routes tab.',
        );
        self::assertStringContainsString(
            'No application routes registered',
            $html,
            'An empty current route collection must render a dedicated inventory state.',
        );
        self::assertStringNotContainsString(
            'yii-debug-route-match',
            $html,
            'An unmatched request must not fabricate a matched-route marker.',
        );
        self::assertStringNotContainsString(
            'Captured route metadata must be an array or null.',
            $html,
            'A deliberately absent matched route must not be reported as malformed metadata.',
        );
    }

    public function testRenderUsesSharedHeaderExchangeAndGroupedServerEnvironment(): void
    {
        $data = RequestSnapshot::fromArray($this->payload(), '$.panels.request')->data();

        $data['requestHeaders'] = [
            'Accept' => 'text/html',
            'X-Trace' => ['first', 'second'],
        ];
        $data['responseHeaders'] = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Set-Cookie' => ['theme=dark', 'session=[redacted]'],
        ];
        $data['SERVER'] = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8081,
            'SERVER_SOFTWARE' => 'PHP Development Server',
            'SCRIPT_FILENAME' => '/app/public/index.php',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_ACCEPT' => 'text/html',
            'APP_ENV' => 'debug',
        ];

        $html = (new RequestPanel())
            ->render(RequestSnapshot::capture($data)
            ->jsonSerialize());

        self::assertStringContainsString(
            'yii-debug-header-exchange',
            $html,
            'Yii3 must consume the shared directional Header exchange.',
        );
        self::assertMatchesRegularExpression(
            '~Inbound.*Request headers.*Outbound.*Response headers~s',
            $html,
            'Request and response headers must retain their HTTP direction.',
        );
        self::assertMatchesRegularExpression(
            '~X-Trace.*2 values.*first.*second~s',
            $html,
            'Repeated PSR header values must remain distinct and ordered.',
        );
        self::assertStringContainsString(
            'yii-debug-server-environment',
            $html,
            'Yii3 must consume the shared grouped Server environment.',
        );
        self::assertMatchesRegularExpression(
            '~Server details.*Network &amp; transport.*Runtime &amp; paths.*Environment &amp; other.*Raw server variables~s',
            $html,
            'Yii3 must retain additional diagnostics and the complete raw view.',
        );
        self::assertStringNotContainsString(
            'Execution context',
            $html,
            'The redundant server summary must be absent.',
        );
        self::assertStringNotContainsString(
            'Additional header variables',
            $html,
            'An exact header duplicate must appear only in raw data.',
        );
        self::assertMatchesRegularExpression(
            '~<details(?=[^>]*aria-label="Raw server variables")(?![^>]*\sopen(?:\s|=|>))[^>]*>~',
            $html,
            'Raw variables must start collapsed.',
        );
        self::assertSame(
            4,
            substr_count($html, 'class="yii-debug-server-group yii-debug-server-group-disclosure"'),
            'Three additional groups and the raw view must remain available.',
        );
        self::assertStringContainsString(
            'aria-label="Filter Raw server variables"',
            $html,
            'The raw view needs its own filter.',
        );
        self::assertMatchesRegularExpression(
            '~<details class="yii-debug-disclosure" open>\s*<summary[^>]*>\s*'
            . '<span class="yii-debug-disclosure-title">Get</span>~s',
            $html,
            'A populated Yii3 Input bucket must start open.',
        );
    }

    public function testRenderWithSummaryKeepsRequestAndRouteIdentityAboveTheCanonicalTabs(): void
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

        $overview = strpos($html, 'yii-debug-request-overview');
        $tabs = strpos($html, 'yii-debug-request-tabs');

        self::assertNotFalse(
            $overview,
            'The shared request execution overview must be rendered.');
        self::assertNotFalse(
            $tabs,
            'The canonical Request tabs must be rendered.',
        );
        self::assertLessThan(
            $tabs,
            $overview,
            'The execution overview must remain visible above every tab.',
        );
        self::assertStringContainsString(
            'https://example.test/orders?page=2',
            $html,
            'The overview must expose the selected request URL.',
        );
        self::assertStringContainsString(
            '127.0.0.1',
            $html,
            'The overview must expose the captured client IP.',
        );
        self::assertStringContainsString(
            '9.0 ms',
            $html,
            'The overview must expose request duration.',
        );
        self::assertStringContainsString(
            'orders/view',
            $html,
            'The overview must expose the resolved route.',
        );
        self::assertStringContainsString(
            'App\\Web\\OrderAction',
            $html,
            'The overview must expose the dispatched action.',
        );
        self::assertStringContainsString(
            'yii-debug-status-2xx',
            $html,
            'The overview must retain the semantic HTTP status treatment.',
        );
        self::assertMatchesRegularExpression(
            '~>Input</a>.*>Headers</a>.*>Session</a>.*>Server</a>~s',
            $html,
            'The shared panel must retain the canonical Request tab order.',
        );
        self::assertStringNotContainsString(
            "<h2>\nRouting\n</h2>",
            $html,
            'The overview must remain the sole route execution summary.',
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

    public function testToolbarItemsExposeTheResolvedRouteBeforeTheStatusCode(): void
    {
        $items = (new RequestPanel())
            ->toolbarItems(
                RequestSnapshot::capture(
                    [
                        'route' => 'orders/view',
                        'statusCode' => 200,
                    ],
                )->jsonSerialize(),
            );

        self::assertCount(
            2,
            $items,
            'A resolved route and response status must share the Request toolbar panel.',
        );
        self::assertSame(
            'orders/view',
            $items[0]->value,
            'The resolved route must be the first Request toolbar metric.',
        );
        self::assertSame(
            'Resolved route: orders/view',
            $items[0]->title,
            'The route metric must expose an explanatory tooltip.',
        );
        self::assertSame(
            '200',
            $items[1]->value,
            'The status code must remain the second Request toolbar metric.',
        );
        self::assertSame(
            'status-2xx',
            $items[1]->status,
            'The combined status metric must retain its HTTP status token.',
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
