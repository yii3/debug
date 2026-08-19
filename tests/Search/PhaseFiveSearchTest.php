<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Search;

use PHPForge\Debug\Panel\Dump\DumpRow;
use PHPForge\Debug\Panel\Mail\MailMessage;
use PHPForge\Debug\Panel\Queue\JobRecord;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Search\{DumpSearch, MailSearch, QueueSearch};

/**
 * Unit tests for the Dump, Mail, and Queue query vocabularies introduced for Phase 5 parity.
 */
final class PhaseFiveSearchTest extends TestCase
{
    public function testDumpSearchUsesTheSharedLogFilterGroup(): void
    {
        $match = new DumpRow('array payload', 1, 'demo', 1.0, []);
        $search = DumpSearch::fromQueryParams(['Log' => ['category' => 'DEMO', 'message' => 'payload']]);

        self::assertSame([$match], $search->filter([$match, new DumpRow('other', 1, 'application', 2.0, [])]), 'Dump filters must match category and message partially.');
    }

    public function testMailSearchMatchesAddressArraysAndTextFields(): void
    {
        $match = $this->mail('Phase 5', ['ada@example.test']);
        $search = MailSearch::fromQueryParams(['Mail' => ['to' => 'ADA@', 'subject' => 'phase']]);

        self::assertSame(['to' => 'ADA@', 'subject' => 'phase'], $search->activeFilters, 'Active mail filters must be normalized.');
        self::assertSame([$match], $search->filter([$match, $this->mail('Other', ['bob@example.test'])]), 'Mail filters must match array and scalar fields partially.');
    }

    public function testQueueSearchCombinesExactAndPartialConditions(): void
    {
        $match = $this->queueRecord('push', 'emails', 'SendMail');
        $search = QueueSearch::fromQueryParams(
            ['Queue' => ['eventType' => 'push', 'componentId' => 'emails', 'jobClass' => 'mail']],
        );

        self::assertSame(
            [$match],
            $search->filter([$match, $this->queueRecord('exec', 'reports', 'GenerateReport')]),
            'Queue event/component filters must be exact while job class remains partial.',
        );
    }

    /**
     * @param list<string> $to
     */
    private function mail(string $subject, array $to): MailMessage
    {
        return new MailMessage('sender@example.test', $to, [], [], [], $subject, 'body', '', 'UTF-8', '', true, 1);
    }

    private function queueRecord(string $eventType, string $componentId, string $jobClass): JobRecord
    {
        return new JobRecord(
            $eventType,
            $componentId,
            'Sync',
            'Yiisoft\\Queue\\SyncQueueProducer',
            false,
            $jobClass,
            [],
            1.0,
            'id',
            null,
            null,
            null,
            null,
            null,
            '',
        );
    }
}
