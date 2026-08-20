<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use InvalidArgumentException;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Panel\{ConfigPanel, LogPanel, ProfilingPanel, RequestPanel};
use Yii3\Debug\Tests\Support\{CustomPanel, GridFactory};
use Yii3\Debug\ToolbarDataFactory;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\{AssetLoader, AssetManager, AssetPublisher};

use const PHP_VERSION;

/**
 * Unit tests for {@see ToolbarDataFactory} aggregating panel chips into shared toolbar payloads.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarDataFactoryTest extends TestCase
{
    public function testCreateAddsFilteredPanelLinksToLogSeverityChips(): void
    {
        $factory = new ToolbarDataFactory(
            $this->assetManager(),
            [new LogPanel(GridFactory::panelGrid())],
        );
        $snapshot = new DebugSnapshot(
            $this->summary(),
            [
                'log' => LogSnapshot::capture(
                    [
                        ['error', 0x01, 'app', 1.0, [], 0],
                        ['warning', 0x02, 'app', 2.0, [], 0],
                    ],
                )->jsonSerialize(),
            ],
            [],
        );
        $items = $factory->create($snapshot)->jsonSerialize()['items'][0]['items'] ?? [];

        self::assertSame(
            '/debug/view?tag=request-1&panel=log&Log%5Blevel%5D=1',
            $items[1]['url'] ?? null,
            'The Errors chip must deep-link to the error-level filter.',
        );
        self::assertSame('errors', $items[1]['id'] ?? null, 'Error links must use stable semantic metadata.');
        self::assertSame(
            '/debug/view?tag=request-1&panel=log&Log%5Blevel%5D=2',
            $items[2]['url'] ?? null,
            'The Warnings chip must deep-link to the warning-level filter.',
        );
        self::assertSame('warnings', $items[2]['id'] ?? null, 'Warning links must use stable semantic metadata.');
    }
    public function testCreateAggregatesPanelChipsInRegistrationOrder(): void
    {
        $factory = new ToolbarDataFactory(
            $this->assetManager(),
            [new ConfigPanel(), new RequestPanel(), new ProfilingPanel()],
        );
        $payload = $factory->create($this->snapshot())->jsonSerialize();
        $panels = $payload['items'];

        self::assertSame('request-1', $payload['tag'], 'Request tag must identify the captured snapshot.');
        self::assertCount(2, $panels, 'Chip-less configuration panel must be omitted.');
        self::assertSame('request', $panels[0]['id'], 'First chip panel must describe the HTTP request.');
        self::assertSame('Request', $panels[0]['title'], 'Request envelope must carry the panel label.');
        self::assertSame(
            '/debug/view?tag=request-1&panel=request',
            $panels[0]['url'] ?? null,
            'Envelope must deep-link to the panel view.',
        );
        self::assertSame('request', $panels[0]['icon'] ?? null, 'Envelope must carry the shared icon name.');

        $statusChip = $panels[0]['items'][0] ?? null;

        self::assertIsArray($statusChip, 'Request panel must expose its status chip.');
        self::assertSame('200', $statusChip['value'], 'Status chip must expose the response code.');
        self::assertSame('status-2xx', $statusChip['status'], 'Status chip must carry its class token.');
        self::assertSame('Status code: 200 OK', $statusChip['title'] ?? null, 'Status chip must describe the response.');
        self::assertSame('profiling', $panels[1]['id'], 'Second chip panel must supply profiling metrics.');
        self::assertSame('', $panels[1]['title'], 'Profiling envelope title must stay blank.');

        $timeChip = $panels[1]['items'][0] ?? null;
        $memoryChip = $panels[1]['items'][1] ?? null;

        self::assertIsArray($timeChip, 'Profiling panel must expose its time chip.');
        self::assertIsArray($memoryChip, 'Profiling panel must expose its memory chip.');
        self::assertSame('13 ms', $timeChip['value'], 'Time chip must render milliseconds.');
        self::assertSame('2.000 MB', $memoryChip['value'], 'Memory chip must render megabytes.');
        self::assertSame('/debug', $payload['indexUrl'], 'History URL must use the configured prefix.');
        self::assertSame('/debug/php-info', $payload['phpInfoUrl'], 'PHP chip must link to the phpinfo endpoint.');
        self::assertIsString($payload['logo'], 'Toolbar logo must be present.');
        self::assertStringContainsString(
            '/svg/yii.svg',
            $payload['logo'],
            'Toolbar logo must use the Yii3-published shared asset.',
        );
        self::assertSame($payload['logo'], $payload['logoFallback'], 'Logo fallback must match the primary asset.');
        self::assertStringContainsString(
            '/svg/',
            $payload['iconBaseUrl'],
            'Toolbar icons must use the Yii3-published shared asset directory.',
        );
    }

    public function testCreateFallsBackToRuntimeVersionsWithoutConfigurationPayload(): void
    {
        $factory = new ToolbarDataFactory($this->assetManager(), [new RequestPanel()]);
        $payload = $factory->create($this->snapshot())->jsonSerialize();

        self::assertSame(PHP_VERSION, $payload['phpVersion'], 'PHP version must fall back to the runtime value.');
        self::assertSame('3', $payload['yiiVersion'], 'Yii version must fall back to the generation marker.');
        self::assertSame(
            '/debug/view?tag=request-1&panel=summary',
            $payload['configUrl'],
            'Brand chip must fall back to the request summary.',
        );
    }

    public function testCreateForwardsPositionAndHeightAndCustomPanelChips(): void
    {
        $factory = new ToolbarDataFactory(
            $this->assetManager(),
            [new CustomPanel()],
            '/debug',
            'upper',
            65,
        );
        $payload = $factory->create($this->snapshot())->jsonSerialize();

        self::assertSame('upper', $payload['position'], 'Configured position must reach the payload.');
        self::assertSame(65, $payload['defaultHeight'], 'Configured height must reach the payload.');
        self::assertCount(1, $payload['items'], 'Custom panel chip must be aggregated.');
        self::assertSame('app.example', $payload['items'][0]['id'], 'Custom panel must keep its stable ID.');

        $customChip = $payload['items'][0]['items'][0] ?? null;

        self::assertIsArray($customChip, 'Custom panel must expose its chip.');
        self::assertSame('1', $customChip['value'], 'Custom chip must reflect stored data.');
    }

    public function testCreateOmitsPanelsWithoutStoredPayload(): void
    {
        $factory = new ToolbarDataFactory($this->assetManager(), [new ProfilingPanel(), new CustomPanel()]);
        $snapshot = new DebugSnapshot($this->summary(), ['app.example' => ['value' => 'stored']], []);
        $payload = $factory->create($snapshot)->jsonSerialize();

        self::assertCount(1, $payload['items'], 'Panels without stored payload must be omitted.');
        self::assertSame('app.example', $payload['items'][0]['id'], 'Stored panel must remain aggregated.');
    }

    public function testCreateReadsVersionsAndConfigUrlFromConfigurationPayload(): void
    {
        $factory = new ToolbarDataFactory($this->assetManager(), [new ConfigPanel()]);
        $snapshot = new DebugSnapshot(
            $this->summary(),
            [
                'config' => ConfigSnapshot::capture(
                    ['phpVersion' => '8.9.9-test', 'yiiVersion' => '3.1-test'],
                )->jsonSerialize(),
            ],
            [],
        );
        $payload = $factory->create($snapshot)->jsonSerialize();

        self::assertSame('8.9.9-test', $payload['phpVersion'], 'PHP version must come from the stored payload.');
        self::assertSame('3.1-test', $payload['yiiVersion'], 'Yii version must come from the stored payload.');
        self::assertSame(
            '/debug/view?tag=request-1&panel=config',
            $payload['configUrl'],
            'Brand chip must deep-link to the configuration panel.',
        );
    }

    public function testCreateSurfacesCollectorFailureAsDangerChip(): void
    {
        $factory = new ToolbarDataFactory($this->assetManager(), [new RequestPanel()]);
        $snapshot = new DebugSnapshot(
            $this->summary(),
            [],
            ['request' => PanelFailure::fromThrowable(PanelFailure::CAPTURE, new RuntimeException('capture broke'))],
        );
        $payload = $factory->create($snapshot)->jsonSerialize();

        self::assertCount(1, $payload['items'], 'Failed panel must stay visible in the toolbar.');

        $failureChip = $payload['items'][0]['items'][0] ?? null;

        self::assertIsArray($failureChip, 'Failed panel must expose its chip.');
        self::assertSame('error', $failureChip['value'], 'Failure chip must read `error`.');
        self::assertSame('danger', $failureChip['status'], 'Failure chip must use danger status.');
        self::assertSame(
            'capture broke',
            $failureChip['title'] ?? null,
            'Failure chip must expose the exception message.',
        );
    }

    public function testThrowInvalidArgumentExceptionForDuplicatePanelId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate Yii3 debug panel ID: request.');

        new ToolbarDataFactory($this->assetManager(), [new RequestPanel(), new RequestPanel()]);
    }

    /**
     * Creates an asset manager that publishes into the test runtime.
     *
     * @return AssetManager Configured asset manager.
     */
    private function assetManager(): AssetManager
    {
        $aliases = new Aliases(
            [
                '@assets' => sys_get_temp_dir() . '/yii3-debug-toolbar-assets',
                '@assetsUrl' => '/debug-assets',
                '@vendor' => dirname(__DIR__) . '/vendor',
            ],
        );

        return (new AssetManager($aliases, new AssetLoader($aliases)))
            ->withPublisher(new AssetPublisher($aliases));
    }

    /**
     * Creates a representative adapted Yii3 snapshot.
     *
     * @return DebugSnapshot Representative snapshot.
     */
    private function snapshot(): DebugSnapshot
    {
        return new DebugSnapshot(
            $this->summary(),
            [
                'request' => ['data' => [], 'statusCode' => 200],
                'profiling' => ['memory' => 2_097_152, 'time' => 0.0125, 'entries' => [], 'samples' => []],
                'app.example' => ['value' => 'custom payload'],
            ],
            [],
        );
    }

    /**
     * Creates a representative request summary.
     *
     * @return RequestSummary Representative summary.
     */
    private function summary(): RequestSummary
    {
        return new RequestSummary(
            tag: 'request-1',
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: 0.0125,
            peakMemory: 2_097_152,
        );
    }
}
