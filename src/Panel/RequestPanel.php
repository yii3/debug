<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Helper\Vocabulary;
use PHPForge\Debug\Panel\Request\{RequestDataNormalizer, RequestSectionRenderer, RequestSnapshot};
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yiisoft\Http\Status;

use function is_int;
use function rtrim;

/**
 * Presents the shared Request panel payload with the framework-neutral hero header and tab sections.
 */
final readonly class RequestPanel implements PanelInterface
{
    use PanelContentTrait;

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
        $data = RequestSnapshot::fromArray($payload, '$.panels.request')->data();

        $view = RequestDataNormalizer::fromPanelData($data, null);

        return RequestSectionRenderer::renderHero($view->hero) . RequestSectionRenderer::renderTabs($view->tabs);
    }

    public function toolbarItems(array $payload): array
    {
        $statusCode = $payload['statusCode'] ?? null;

        if (!is_int($statusCode)) {
            return [];
        }

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
}
