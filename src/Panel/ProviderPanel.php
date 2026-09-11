<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\{Panel as PortablePanel, PanelView};
use PHPForge\Debug\Panel\PanelRenderer;
use PHPForge\Debug\Toolbar\ToolbarItem;
use Throwable;

/**
 * Adapts any provider-owned declarative panel to the Yii3 debugger.
 */
class ProviderPanel implements ToolbarPanelProviderInterface
{
    /**
     * @var array<string, mixed>|null
     */
    private array|null $preparedPayload = null;
    private PanelView|null $preparedView = null;
    private Throwable|null $presentationFailure = null;

    public function __construct(private readonly PortablePanel $provider) {}
    /**
     * Creates a render-operation-local adapter. Hosts discard it after one page, including on failure.
     *
     * @param array<string, mixed> $payload
     */
    public function forPayload(array $payload): self
    {
        $prepared = clone $this;
        $prepared->preparedPayload = $payload;
        $prepared->preparedView = null;
        $prepared->presentationFailure = null;
        try {
            $prepared->preparedView = $this->provider->present($this->data($payload));
        } catch (Throwable $failure) {
            $prepared->presentationFailure = $failure;
        }
        return $prepared;
    }
    public function hasContent(array $payload): bool
    {
        return $this->view($payload)->isActive();
    }
    public function icon(): string
    {
        return $this->provider->icon();
    }
    public function id(): string
    {
        return $this->provider->id();
    }
    public function name(): string
    {
        return $this->provider->name();
    }
    public function render(array $payload): string
    {
        return PanelRenderer::render($this->name(), $this->view($payload));
    }
    public function toolbarItems(array $payload): array
    {
        $items = [];
        foreach ($this->view($payload)->toolbarMetrics() as $metric) {
            $items[] = ToolbarItem::create($metric['value']['value'])->withTitle($metric['label']);
        }
        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function data(array $payload): array
    {
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function view(array $payload): PanelView
    {
        if ($this->preparedPayload === $payload) {
            if ($this->presentationFailure !== null) {
                throw $this->presentationFailure;
            }
            if ($this->preparedView !== null) {
                return $this->preparedView;
            }
        }
        return $this->provider->present($this->data($payload));
    }
}
