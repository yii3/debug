<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\{PanelIcon, PanelTitle};
use PHPForge\Debug\Panel\Request\{RequestDataNormalizer, RequestRenderer, RequestSnapshot, RequestToolbarItemFactory};
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yii3\Debug\Routing\RequestRoutingViewFactory;
use Yiisoft\Http\Status;
use Yiisoft\Router\RouteCollectionInterface;

use function is_string;

/**
 * Presents the captured PSR-7 request and response with the shared Yii Request panel UI.
 */
final readonly class RequestPanel implements ToolbarPanelProviderInterface
{
    /**
     * @param RouteCollectionInterface|null $routes Live route collection the overview reads its matched definition and
     * configuration badges from, or `null` when the application registers no router.
     */
    public function __construct(private RouteCollectionInterface|null $routes = null) {}

    /**
     * Reports whether the capture recorded request data worth opening the panel for.
     *
     * @param array<string, mixed> $payload Serialized Request panel payload.
     *
     * @return bool `true` when the capture carried request data; `false` otherwise.
     */
    public function hasContent(array $payload): bool
    {
        return $payload !== [];
    }

    /**
     * Returns the icon key shared with the built-in Request navigation entry.
     *
     * @return string Shared Debug Core icon key.
     */
    public function icon(): string
    {
        return PanelIcon::REQUEST->value;
    }

    /**
     * Returns the identifier associating the panel with its slice of the captured payload.
     *
     * @return string Stable panel identifier.
     */
    public function id(): string
    {
        return 'request';
    }

    /**
     * Returns the panel title shown in the debugger navigation.
     *
     * @return string Human-readable panel name.
     */
    public function name(): string
    {
        return PanelTitle::REQUEST->value;
    }

    /**
     * Renders the detail view, letting the manifest entry fill the identity the capture omitted.
     *
     * @param PanelRenderInput $input Payload, request context, and request summary of the page being rendered.
     *
     * @return string Rendered panel markup.
     */
    public function render(PanelRenderInput $input): string
    {
        $data = self::snapshot($input->payload)->data();

        return RequestRenderer::render(
            RequestDataNormalizer::fromPanelData($data, $input->summary),
            RequestRoutingViewFactory::fromRequestData($data, $this->routes),
        );
    }

    /**
     * Builds the toolbar metrics: the resolved route and the response status.
     *
     * @param array<string, mixed> $payload Serialized Request panel payload.
     *
     * @return list<ToolbarItem> Toolbar metrics in display order.
     */
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
     * Narrows the serialized payload into the typed capture the panel reads.
     *
     * @param array<string, mixed> $payload Serialized Request panel payload.
     *
     * @return RequestSnapshot Typed capture.
     */
    private static function snapshot(array $payload): RequestSnapshot
    {
        return RequestSnapshot::fromArray($payload, '$.panels.request');
    }
}
