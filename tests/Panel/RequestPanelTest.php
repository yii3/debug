<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\RequestPanel;

/**
 * Unit tests for {@see RequestPanel} toolbar status chips derived from the shared Request payload.
 */
final class RequestPanelTest extends TestCase
{
    public function testRenderHydratesTheSerializedPayloadAndShowsRoutingData(): void
    {
        $snapshot = RequestSnapshot::capture(
            [
                'action' => 'App\Web\HomePage\Action',
                'actionParams' => ['id' => '7'],
                'flashes' => [],
                'general' => ['method' => 'GET'],
                'requestBody' => [],
                'requestHeaders' => [],
                'responseHeaders' => [],
                'route' => 'home',
                'statusCode' => 200,
                'COOKIE' => [],
                'FILES' => [],
                'GET' => [],
                'POST' => [],
                'SERVER' => [],
                'SESSION' => [],
            ],
        );

        $html = (new RequestPanel())->render($snapshot->jsonSerialize());

        self::assertStringContainsString('home', $html, 'Route name must surface in the Routing section.');
        self::assertStringContainsString('HomePage', $html, 'Action descriptor must surface.');
        self::assertStringNotContainsString('>null<', $html, 'Routing entries must not render as `null`.');
    }

    public function testToolbarItemsExposeStatusChipWithClassTokenAndText(): void
    {
        $items = (new RequestPanel())->toolbarItems(['data' => [], 'statusCode' => 404]);

        self::assertCount(1, $items, 'Exactly one status chip must be emitted.');
        self::assertSame('404', $items[0]->value, 'Chip value must expose the response code.');
        self::assertSame('status-4xx', $items[0]->status, 'Chip must carry its status-class token.');
        self::assertSame('Status code: 404 Not Found', $items[0]->title, 'Tooltip must describe the response.');
    }

    public function testToolbarItemsOmitStatusTextForUnknownCode(): void
    {
        $items = (new RequestPanel())->toolbarItems(['data' => [], 'statusCode' => 599]);

        self::assertSame('Status code: 599', $items[0]->title ?? null, 'Unknown code must render without text.');
    }

    public function testToolbarItemsStayEmptyWithoutIntegerStatusCode(): void
    {
        $panel = new RequestPanel();

        self::assertSame([], $panel->toolbarItems([]), 'Missing status code must not emit a chip.');
        self::assertSame([], $panel->toolbarItems(['statusCode' => '200']), 'String status code must be refused.');
    }
}
