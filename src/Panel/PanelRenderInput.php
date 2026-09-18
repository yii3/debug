<?php

declare(strict_types=1);

namespace Yii3\Debug\Panel;

use PHPForge\Debug\Panel\PanelRenderContext;
use PHPForge\Debug\Storage\RequestSummary;

/**
 * Carries the payload, request context, and request summary a panel renders one captured page from.
 */
final readonly class PanelRenderInput
{
    /**
     * @param array<string, mixed> $payload Serialized panel payload.
     * @param PanelRenderContext $context State of the debugger request being rendered.
     * @param RequestSummary $summary Manifest entry of the capture being rendered.
     */
    public function __construct(
        public array $payload,
        public PanelRenderContext $context,
        public RequestSummary $summary,
    ) {}
}
