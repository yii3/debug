<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use JsonException;
use PHPForge\Debug\Toolbar\ToolbarItem;
use Yii3\Debug\Panel\{PanelContentTrait, PanelInterface};

use function count;
use function htmlspecialchars;
use function json_encode;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;

/**
 * Provides application-defined presentation metadata for the custom collector fixture.
 */
final readonly class CustomPanel implements PanelInterface
{
    use PanelContentTrait;

    public function icon(): string
    {
        return 'config';
    }
    public function id(): string
    {
        return 'app.example';
    }

    public function name(): string
    {
        return 'Application example';
    }

    /**
     * @throws JsonException When the fixture payload cannot be encoded.
     */
    public function render(array $payload): string
    {
        return '<div class="app-example-panel">'
            . htmlspecialchars(json_encode($payload, JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>';
    }

    public function toolbarItems(array $payload): array
    {
        return $payload === [] ? [] : [new ToolbarItem(value: (string) count($payload), label: 'Entries')];
    }
}
