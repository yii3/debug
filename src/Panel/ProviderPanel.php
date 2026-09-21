<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\{Panel as PortablePanel, PanelView};
use PHPForge\Debug\Panel\PanelRenderer;
use PHPForge\Debug\Toolbar\ToolbarItem;
use Throwable;

/**
 * Adapts any provider-owned declarative panel to the Yii3 debugger.
 *
 * The presentation is built on first use and memoized for the payload it was built from, so the listing check, the
 * toolbar metrics, and the detail page of one capture share a single provider call.
 */
class ProviderPanel implements ToolbarPanelProviderInterface
{
    /**
     * Icon key replacing the provider default, or `null` while the provider default applies.
     */
    private string|null $effectiveIcon = null;
    /**
     * Display title replacing the provider default, or `null` while the provider default applies.
     */
    private string|null $effectiveTitle = null;
    /**
     * Payload the memoized presentation was built from, or `null` before the first successful presentation.
     *
     * @var array<string, mixed>|null
     */
    private array|null $memoizedPayload = null;
    /**
     * Presentation memoized for the payload it was built from, or `null` before the first successful presentation.
     */
    private PanelView|null $memoizedView = null;

    /**
     * @param PortablePanel $provider Declarative panel supplying the identity and presentation.
     */
    public function __construct(private readonly PortablePanel $provider) {}

    /**
     * Returns whether the capture holds a presentation for this panel.
     *
     * An extension the application enabled stays listed even on a capture where it recorded no activity, so its own
     * empty state explains the idle capture instead of the entry disappearing from the sidebar. Building the
     * presentation here keeps a failing provider discoverable, because the failure reaches the host, and it primes
     * the memo the toolbar and the detail page read next.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws Throwable if the provider cannot present the payload.
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
     * @return string Effective icon key, falling back to the provider default.
     */
    public function icon(): string
    {
        return $this->effectiveIcon ?? $this->provider->icon();
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
     * @return string Effective display name, falling back to the provider default.
     */
    public function name(): string
    {
        return $this->effectiveTitle ?? $this->provider->name();
    }

    /**
     * Renders the detail content the provider produces for a capture.
     *
     * Only the payload reaches the provider, so the portable presentation stays independent of the host request.
     *
     * @param PanelRenderInput $input Payload, request context, and request summary of the page being rendered.
     *
     * @throws Throwable if the provider cannot present the payload.
     *
     * @return string Rendered detail content.
     */
    public function render(PanelRenderInput $input): string
    {
        return PanelRenderer::render($this->name(), $this->view($input->payload));
    }

    /**
     * Builds the toolbar metrics the provider declares for a capture.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws Throwable if the provider cannot present the payload.
     *
     * @return list<ToolbarItem> Toolbar metrics declared by the provider.
     */
    public function toolbarItems(array $payload): array
    {
        $items = [];

        foreach ($this->view($payload)->toolbarMetrics() as $metric) {
            $items[] = ToolbarItem::create($metric->value)->withTitle($metric->label);
        }

        return $items;
    }

    /**
     * Returns a copy presenting the panel under the metadata the application configured.
     *
     * The detail heading, the sidebar entry, and the toolbar chip all read the effective title, so the panel keeps
     * one name across the debugger while {@see self::id()} stays the stable capture key.
     *
     * @param string $title Effective display title.
     * @param string $icon Effective icon key.
     *
     * @return self Adapter presenting the configured metadata, with an empty memo.
     */
    public function withMetadata(string $title, string $icon): self
    {
        $new = clone $this;

        $new->effectiveIcon = $icon;
        $new->effectiveTitle = $title;
        $new->memoizedPayload = null;
        $new->memoizedView = null;

        return $new;
    }

    /**
     * Builds the provider presentation for a payload, memoizing it until a different payload arrives.
     *
     * A failure is never memoized, so the next call presents the payload again instead of replaying a stale error.
     *
     * @param array<string, mixed> $payload Serialized panel payload.
     *
     * @throws Throwable if the provider cannot present the payload.
     *
     * @return PanelView Presentation built by the provider.
     */
    private function view(array $payload): PanelView
    {
        if ($this->memoizedView !== null && $this->memoizedPayload === $payload) {
            return $this->memoizedView;
        }

        $this->memoizedPayload = null;
        $this->memoizedView = null;

        $view = $this->provider->present($payload);

        $this->memoizedPayload = $payload;
        $this->memoizedView = $view;

        return $view;
    }
}
