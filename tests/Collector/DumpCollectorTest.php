<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Collector\DumpCollector;

/**
 * Unit tests for {@see DumpCollector} covering lifecycle, rendering, categories, and call-site traces.
 */
#[Group('collector')]
#[Group('dump')]
final class DumpCollectorTest extends TestCase
{
    public function testCaptureReturnsNullBeforeStartupAndAfterShutdown(): void
    {
        $collector = new DumpCollector();

        self::assertNull($collector->capture(), 'Idle collectors must not expose a snapshot.');

        $collector->startup();
        $collector->shutdown();

        self::assertNull($collector->capture(), 'Shutdown must deactivate and clear the collector.');
    }

    public function testCollectCanRenderEscapedPlainText(): void
    {
        $collector = new DumpCollector(highlight: false);
        $collector->startup();
        $collector->collect('<script>');

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must produce a snapshot.');
        $row = $snapshot->entries()[0] ?? self::fail('Expected one captured dump.');
        self::assertSame(
            '&#039;&lt;script&gt;&#039;',
            $row->message,
            'Plain dumps must be escaped before trusted rendering.',
        );
    }

    public function testCollectCapturesHighlightedValueCategoryAndTrace(): void
    {
        $collector = new DumpCollector();
        $collector->startup();
        $collector->collect(['answer' => 42], 'demo');

        $snapshot = $collector->capture();

        self::assertNotNull($snapshot, 'Started collector must produce a snapshot.');
        $rows = $snapshot->entries();
        $row = $rows[0] ?? self::fail('Expected one captured dump.');

        self::assertSame('demo', $row->category, 'Explicit category must round-trip.');
        self::assertStringContainsString('answer', $row->message, 'Highlighted payload must contain the array key.');
        self::assertNotEmpty($row->trace, 'Application call site must be captured.');
    }
}
