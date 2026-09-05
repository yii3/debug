<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\Request\{
    RequestDataNormalizer,
    RequestRenderer,
    RequestSnapshot,
    RequestToolbarItemFactory,
};
use PHPForge\Debug\Storage\RequestSummary;
use Yii3\Debug\Routing\RequestRoutingViewFactory;
use Yiisoft\Http\Status;
use Yiisoft\Router\RouteCollectionInterface;

use function is_string;

/**
 * Presents the captured PSR-7 request and response with the shared Yii Request panel UI.
 */
final readonly class RequestPanel implements SummaryAwarePanelInterface, ToolbarPanelProviderInterface
{
    public function __construct(private RouteCollectionInterface|null $routes = null) {}

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
        $snapshot = self::snapshot($payload);
        $data = $snapshot->data();

        return RequestToolbarItemFactory::create(
            route: is_string($data['route'] ?? null) ? $data['route'] : '',
            statusCode: $snapshot->statusCode,
            statusText: Status::TEXTS[$snapshot->statusCode] ?? '',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderView(array $payload, RequestSummary|null $summary): string
    {
        $data = self::snapshot($payload)->data();
        $view = RequestDataNormalizer::fromPanelData($data, $summary);

        return RequestRenderer::render(
            $view,
            RequestRoutingViewFactory::fromRequestData($data, $this->routes),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function snapshot(array $payload): RequestSnapshot
    {
        return RequestSnapshot::fromArray($payload, '$.panels.request');
    }
}
