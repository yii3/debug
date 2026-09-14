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
    /**
     * Presentation built for the payload this adapter was created for, or `null` before preparation.
     */
    private PanelView|null $preparedView = null;
    /**
     * Failure raised while building the presentation, surfaced instead of breaking the page.
     */
    private Throwable|null $presentationFailure = null;

    /**
     * @param PortablePanel $provider Declarative panel supplying the identity and presentation.
     */
    public function __construct(private readonly PortablePanel $provider) {}

    /**
     * Creates a render-operation-local adapter. Hosts discard it after one page, including on failure.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return self Adapter bound to that payload.
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

    /**
     * Returns whether the capture holds a presentation for this panel.
     *
     * An extension the application enabled stays listed even on a capture where it recorded no activity, so its own
     * empty state explains the idle capture instead of the entry disappearing from the sidebar. Building the
     * presentation here keeps a failing provider discoverable, because the failure reaches the host.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws Throwable If the provider cannot present the payload.
     *
     * @return bool `true`, since the host only asks once the capture carries this panel's payload.
     */
    public function hasContent(array $payload): bool
    {
        $this->view($payload);

        return true;
    }

    /**
     * Returns the shared Debug Core icon key.
     *
     * @return string Icon key declared by the provider.
     */
    public function icon(): string
    {
        return $this->provider->icon();
    }

    /**
     * Returns the stable identifier of this panel.
     *
     * @return string Stable panel ID declared by the provider.
     */
    public function id(): string
    {
        return $this->provider->id();
    }

    /**
     * Returns the human-readable panel name.
     *
     * @return string Panel display name declared by the provider.
     */
    public function name(): string
    {
        return $this->provider->name();
    }

    /**
     * Renders the detail content the provider produces for a capture.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return string Rendered detail content.
     */
    public function render(array $payload): string
    {
        return PanelRenderer::render($this->name(), $this->view($payload));
    }

    /**
     * Builds the toolbar metrics the provider declares for a capture.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return list<ToolbarItem> Toolbar metrics declared by the provider.
     */
    public function toolbarItems(array $payload): array
    {
        $items = [];

        foreach ($this->view($payload)->toolbarMetrics() as $metric) {
            $items[] = ToolbarItem::create($metric['value']['value'])->withTitle($metric['label']);
        }

        return $items;
    }

    /**
     * Returns the payload handed to the provider, as a hook subclasses override to transform it.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return array<string, mixed> Payload unchanged; a subclass may return a transformed one.
     */
    protected function data(array $payload): array
    {
        return $payload;
    }

    /**
     * Builds the provider presentation for a payload, memoizing it per adapter.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @return PanelView Presentation built by the provider, memoized per adapter.
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
