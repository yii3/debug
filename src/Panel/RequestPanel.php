<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\Vocabulary;
use PHPForge\Debug\Panel\Request\{RequestDataNormalizer, RequestSectionRenderer, RequestSnapshot};
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yiisoft\Http\Status;

use function rtrim;

/**
 * Presents the captured PSR-7 request and response with the shared Yii Request panel UI.
 */
final readonly class RequestPanel implements SummaryAwarePanelInterface, ToolbarPanelProviderInterface
{
    public function hasContent(array $payload): bool
    {
        return $payload !== [];
    }

    public function icon(): string
    {
        return 'request';
    }

    public function id(): string
    {
        return 'request';
    }

    public function name(): string
    {
        return 'Request';
    }

    public function render(array $payload): string
    {
        return $this->renderView($payload, null);
    }

    public function renderWithSummary(array $payload, RequestSummary $summary): string
    {
        return $this->renderView($payload, $summary);
    }

    public function toolbarItems(array $payload): array
    {
        $statusCode = self::snapshot($payload)->statusCode;
        $statusClass = Vocabulary::statusClass($statusCode);
        $statusText = Status::TEXTS[$statusCode] ?? '';

        return [
            new ToolbarItem(
                value: (string) $statusCode,
                status: $statusClass === 'none' ? 'default' : "status-{$statusClass}",
                title: rtrim("Status code: {$statusCode} {$statusText}"),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderView(array $payload, RequestSummary|null $summary): string
    {
        $view = RequestDataNormalizer::fromPanelData(self::snapshot($payload)->data(), $summary);

        return RequestSectionRenderer::renderHero($view->hero)
            . RequestSectionRenderer::renderTabs($view->tabs);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): RequestSnapshot
    {
        return RequestSnapshot::fromArray($payload, '$.panels.request');
    }
}
