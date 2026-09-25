<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Mail\MailPanel as CoreMailPanel;
use PHPForge\Debug\Panel\PanelRenderer;
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore};
use PHPForge\Debug\Toolbar\ToolbarItem;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yii3\Debug\Panel\MailPanel;
use Yii3\Debug\Tests\Support\{HelperFactory, TemporaryDirectory};

use function array_map;
use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see MailPanel} content detection, the direct and cross-request toolbar metric, and rendering of a
 * capture written by the Yii2 host.
 */
final class MailPanelTest extends TestCase
{
    private string $root = '';

    public function testHasContentReportsEveryCapturedPayload(): void
    {
        $panel = $this->panel();

        self::assertTrue(
            $panel->hasContent(['entries' => []]),
            'Idle capture must stay listed, as in Yii2.',
        );
        self::assertFalse(
            $panel->hasContent([]),
            'Empty payload has nothing to show.',
        );
    }

    public function testRenderMatchesTheYii2DetailForAYii2Capture(): void
    {
        $payload = self::yii2Capture()['panels']['mail'];

        self::assertSame(
            PanelRenderer::render('Mail', (new CoreMailPanel())->present($payload)),
            $this->panel()->render(HelperFactory::createPanelRenderInput($payload)),
            'Yii3 must render the Yii2 capture exactly like the Yii2 panel.',
        );
    }

    public function testToolbarItemsCountDirectMessagesOnly(): void
    {
        $panel = $this->panel();

        self::assertSame(
            [['value' => '1', 'status' => 'default']],
            self::serialize($panel->toolbarItems(self::yii2Capture()['panels']['mail'])),
            'Direct count must match the Yii2 metric.',
        );
        self::assertSame(
            [],
            $panel->toolbarItems(['entries' => []]),
            'No message means no metric.',
        );
    }

    public function testToolbarItemsForCaptureCountsMessagesOfTheCurrentRequest(): void
    {
        $this->write('post', 'POST', 'http://localhost/contact', 1);
        $this->write('current', 'POST', 'http://localhost/contact', 1);

        self::assertSame(
            [['value' => '1', 'status' => 'default']],
            self::serialize(
                $this->panel()->toolbarItemsForCapture('current', self::yii2Capture()['panels']['mail']),
            ),
            'Own messages must win over the previous request.',
        );
    }

    public function testToolbarItemsForCaptureFallsBackToTheNewestEntryWhenTheCurrentTagIsNotListed(): void
    {
        $this->write('older', 'POST', 'http://localhost/older', 3);
        $this->write('newest', 'POST', 'http://localhost/newest', 2);

        self::assertSame(
            [
                [
                    'value' => '2',
                    'status' => 'cross-request',
                    'title' => 'Sent in the previous request (POST /newest) — open it.',
                    'url' => '/debug/view?tag=newest&panel=mail',
                ],
            ],
            self::serialize($this->panel()->toolbarItemsForCapture('unlisted', ['entries' => []])),
            'Unlisted capture must fall back to the newest entry.',
        );
    }

    public function testToolbarItemsForCapturePointsAtThePreviousRequestAfterARedirect(): void
    {
        $this->write('older', 'POST', 'http://localhost/older', 5);
        $this->write('post', 'POST', 'http://localhost/contact?sent=1', 1);
        $this->write('get', 'GET', 'http://localhost/contact', 0);
        $this->write('later', 'GET', 'http://localhost/', 0);

        self::assertSame(
            [
                [
                    'value' => '1',
                    'status' => 'cross-request',
                    'title' => 'Sent in the previous request (POST /contact) — open it.',
                    'url' => '/admin/debug/view?tag=post&panel=mail',
                ],
            ],
            self::serialize(
                $this->panel(new ToolbarOptions('/admin/debug/'))->toolbarItemsForCapture('get', ['entries' => []]),
            ),
            'Metric must link the request that sent the mail.',
        );
    }

    public function testToolbarItemsForCaptureReturnsNothingWhenTheCurrentTagIsTheOldest(): void
    {
        $this->write('current', 'GET', 'http://localhost/', 0);
        $this->write('newer', 'POST', 'http://localhost/contact', 1);

        self::assertSame(
            [],
            $this->panel()->toolbarItemsForCapture('current', ['entries' => []]),
            'A newer request is never the previous one.',
        );
    }

    public function testToolbarItemsForCaptureReturnsNothingWhenThePreviousRequestSentNoMail(): void
    {
        $this->write('post', 'POST', 'http://localhost/contact', 1);
        $this->write('between', 'GET', 'http://localhost/', 0);
        $this->write('get', 'GET', 'http://localhost/contact', 0);

        self::assertSame(
            [],
            $this->panel()->toolbarItemsForCapture('get', ['entries' => []]),
            'Only the immediately previous request counts.',
        );
    }

    public function testToolbarItemsForCaptureReturnsNothingWithoutHistory(): void
    {
        self::assertSame(
            [],
            $this->panel()->toolbarItemsForCapture('current', ['entries' => []]),
            'Missing manifest must yield no metric.',
        );
    }

    public function testToolbarItemsForCaptureShowsTheFullUrlWhenItHasNoPath(): void
    {
        $this->write('post', 'POST', 'http://localhost', 1);
        $this->write('current', 'GET', '', 0);
        $this->write('newest', 'POST', '', 4);

        self::assertSame(
            'Sent in the previous request (POST http://localhost) — open it.',
            self::serialize($this->panel()->toolbarItemsForCapture('current', ['entries' => []]))[0]['title'] ?? null,
            'URL without a path must be shown whole.',
        );
        self::assertSame(
            'Sent in the previous request (POST ) — open it.',
            self::serialize($this->panel()->toolbarItemsForCapture('unlisted', ['entries' => []]))[0]['title'] ?? null,
            'Empty URL must stay empty.',
        );
    }

    protected function setUp(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-mail-panel-');
    }

    protected function tearDown(): void
    {
        TemporaryDirectory::remove($this->root);
    }

    private function panel(ToolbarOptions $options = new ToolbarOptions()): MailPanel
    {
        return new MailPanel(new SnapshotStore($this->root, 0o700, 0o600), $options);
    }

    /**
     * @param list<ToolbarItem> $items
     *
     * @return list<array<string, string>>
     */
    private static function serialize(array $items): array
    {
        return array_map(static fn(ToolbarItem $item): array => $item->jsonSerialize(), $items);
    }

    private function write(string $tag, string $method, string $url, int $mailCount): void
    {
        $summary = RequestSummary::create($tag)
            ->withRequest(url: $url, method: $method, ip: '127.0.0.1', time: 1_700_000_000.0, ajax: false)
            ->withMail($mailCount, []);

        (new SnapshotStore($this->root, 0o700, 0o600))->writeSnapshot(new DebugSnapshot($summary, [], []), 50);
    }

    /**
     * @return array{summary: array<string, mixed>, panels: array{mail: array<string, mixed>}}
     */
    private static function yii2Capture(): array
    {
        /** @var array{summary: array<string, mixed>, panels: array{mail: array<string, mixed>}} */
        return json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/Support/Fixture/yii2-mail-capture.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
