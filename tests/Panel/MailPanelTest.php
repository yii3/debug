<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Mail\{MailMessage, MailSnapshot};
use PHPForge\Debug\Panel\PanelRenderContext;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\MailPanel;
use Yii3\Debug\Tests\Support\GridFactory;
use Yii3\Debug\Web\DebugUrlGenerator;

/**
 * Unit tests for {@see MailPanel} presenting filters, message cards, download links, and failure counts.
 */
final class MailPanelTest extends TestCase
{
    public function testMetadataAndToolbarMatchTheMailContract(): void
    {
        $panel = new MailPanel(GridFactory::panelGrid());
        $items = $panel->toolbarItems($this->snapshot()->jsonSerialize());
        $item = $items[0] ?? self::fail('Expected the mail toolbar item.');

        self::assertSame('mail', $panel->id(), 'Stable ID must pair with the mail collector.');
        self::assertSame('mail', $panel->icon(), 'Icon must use the shared mail glyph.');
        self::assertSame('Mail', $panel->name(), 'Label must match Yii2.');
        self::assertSame('2', $item->value, 'Toolbar must expose the message count.');
        self::assertSame([], $panel->toolbarItems(['entries' => []]), 'Empty mail must omit the toolbar chip.');
    }

    public function testRenderShowsEmptyStateAndFilteredMessageCards(): void
    {
        $panel = new MailPanel(GridFactory::panelGrid());

        self::assertStringContainsString('No emails sent', $panel->render(['entries' => []]), 'Empty state must explain the proxy integration.');

        $html = $panel->renderWithContext(
            $this->snapshot()->jsonSerialize(),
            new PanelRenderContext(
                'request-1',
                'mail',
                ['Mail' => ['subject' => 'welcome']],
                'light',
                new DebugUrlGenerator('/debug'),
            ),
        );

        self::assertStringContainsString('Welcome Ada', $html, 'Matching message card must remain visible.');
        self::assertStringNotContainsString('Failed report', $html, 'Unmatched subject must be filtered out.');
        self::assertStringContainsString('/debug/download-mail?tag=request-1&amp;seq=0', $html, 'Download link must be scoped by snapshot and sequence.');
        self::assertStringContainsString(
            '<strong>1</strong> failed',
            $html,
            'Failure count must include the full captured request.',
        );
        self::assertStringContainsString('name="Mail[subject]"', $html, 'Yii2-compatible filter field must render.');
    }

    private function snapshot(): MailSnapshot
    {
        return new MailSnapshot(
            [
                new MailMessage(
                    'sender@example.test',
                    ['ada@example.test'],
                    [],
                    [],
                    [],
                    'Welcome Ada',
                    'Hello Ada',
                    'X-Test: one',
                    'UTF-8',
                    'message-one.eml',
                    true,
                    1_700_000_000,
                ),
                new MailMessage(
                    'sender@example.test',
                    ['ops@example.test'],
                    [],
                    [],
                    [],
                    'Failed report',
                    'Failure body',
                    '',
                    'UTF-8',
                    'message-two.eml',
                    false,
                    1_700_000_001,
                ),
            ],
        );
    }
}
