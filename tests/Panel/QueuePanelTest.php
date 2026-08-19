<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Panel\Queue\{JobRecord, QueueSnapshot};
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\QueuePanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for {@see QueuePanel} presenting summary metrics, filters, job links, and error toolbar chips.
 */
final class QueuePanelTest extends TestCase
{
    public function testMetadataAndToolbarMatchTheQueueContract(): void
    {
        $panel = new QueuePanel(GridFactory::panelGrid());
        $items = $panel->toolbarItems($this->snapshot()->jsonSerialize());
        $total = $items[0] ?? self::fail('Expected the queue total toolbar item.');
        $errors = $items[1] ?? self::fail('Expected the queue error toolbar item.');

        self::assertSame('queue', $panel->id(), 'Stable ID must pair with the queue collector.');
        self::assertSame('queue', $panel->icon(), 'Icon must use the shared queue glyph.');
        self::assertSame('Queue', $panel->name(), 'Label must match Yii2.');
        self::assertSame('2', $total->value, 'Toolbar must expose the event count.');
        self::assertSame('1', $errors->value, 'Toolbar must expose the error count.');
        self::assertSame('danger', $errors->status, 'Error chip must use danger status.');
        self::assertSame([], $panel->toolbarItems(['entries' => []]), 'Empty queue must omit toolbar chips.');
    }

    public function testRenderShowsEmptyStateAndFilteredQueueGrid(): void
    {
        $panel = new QueuePanel(GridFactory::panelGrid());

        self::assertStringContainsString('No jobs queued', $panel->render(['entries' => []]), 'Empty state must explain the queue decorator.');

        $html = $panel->renderWithContext(
            $this->snapshot()->jsonSerialize(),
            new PanelRenderContext(
                'request-1',
                'queue',
                ['Queue' => ['eventType' => 'error']],
                'light',
                new DebugUrlGenerator('/debug'),
            ),
        );

        self::assertStringContainsString('GenerateReport', $html, 'Matching failed record must remain visible.');
        self::assertStringNotContainsString('SendMail', $html, 'Unmatched push record must be filtered out.');
        self::assertStringContainsString('/debug/queue-job?tag=request-1&amp;seq=1', $html, 'Job link must retain its original sequence.');
        self::assertStringContainsString('yii-debug-active-filters', $html, 'Active queue filter must render.');
    }

    private function record(string $type, string $class, string $error, float $time): JobRecord
    {
        return new JobRecord(
            $type,
            'jobs',
            'Sync',
            'Yiisoft\\Queue\\SyncQueueProducer',
            false,
            $class,
            ['payload' => ['id' => 42]],
            $time,
            'job-1',
            60,
            null,
            null,
            $type === JobRecord::TYPE_PUSH ? null : 1,
            $type === JobRecord::TYPE_PUSH ? null : 0.25,
            $error,
        );
    }

    private function snapshot(): QueueSnapshot
    {
        return new QueueSnapshot(
            [
                $this->record(JobRecord::TYPE_PUSH, 'App\\Message\\SendMail', '', 1.0),
                $this->record(JobRecord::TYPE_ERROR, 'App\\Message\\GenerateReport', 'worker failed', 2.0),
            ],
        );
    }
}
