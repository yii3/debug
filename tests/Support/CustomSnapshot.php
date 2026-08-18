<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use PHPForge\Debug\Storage\PanelSnapshot;

/**
 * Represents the payload captured by the custom collector fixture.
 */
final readonly class CustomSnapshot implements PanelSnapshot
{
    public function __construct(private string $value) {}

    public function jsonSerialize(): array
    {
        return ['value' => $this->value];
    }
}
