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

        self::assertStringNotContainsString(
            '<script>alert(1)</script>',
            $html,
            'Captured request values must never render as executable HTML.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Captured request values must remain inspectable after escaping.',
        );
    }

    public function testRenderWithSummaryMatchesTheSharedYiiRequestLayout(): void
    {
        $summary = RequestSummary::create('request-1')
            ->withRequest(
                'https://example.test/orders?page=2',
                'GET',
                '127.0.0.1',
                1_725_000_756.0,
            )
            ->withResponse(200)
            ->withProfiling(0.009, 1_145_324);

        $html = (new RequestPanel())
            ->renderWithSummary($this->payload(), $summary);

        self::assertStringContainsString(
            'https://example.test/orders?page=2',
            $html,
            'Request hero must use the full URL from the stored summary.',
        );
        self::assertStringContainsString(
            '127.0.0.1',
            $html,
            'Request hero must expose the captured IP.',
        );
        self::assertStringContainsString(
            '9.0 ms',
            $html,
            'Request hero must expose the captured duration.',
        );
        self::assertStringContainsString(
            'yii-debug-request-hero',
            $html,
            'Shared Request hero markup must be used.',
        );
        self::assertStringContainsString(
            'role="tablist"',
            $html,
            'Shared accessible tabs must be rendered.',
        );

        foreach (
            [
                'Parameters',
                'Headers',
                'Session',
                'Server',
            ] as $tab
        ) {
            self::assertStringContainsString(
                ">$tab<",
                $html,
                "The $tab tab must be rendered.",
            );
        }

        foreach (
            [
                'Routing',
                'Get',
                'Post',
                'Files',
                'Cookies',
                'Request Body',
            ] as $section
        ) {
            self::assertStringContainsString(
                $section,
                $html,
                "The $section section must be rendered.",
            );
        }

        self::assertStringContainsString(
            'orders/view',
            $html,
            'Matched route must be rendered.',
        );
        self::assertStringContainsString(
            'OrderAction',
            $html,
            'Resolved action must be rendered.',
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
