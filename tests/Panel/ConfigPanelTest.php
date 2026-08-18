<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\ConfigPanel;

/**
 * Unit tests for {@see ConfigPanel} presenting the shared Configuration payload.
 */
final class ConfigPanelTest extends TestCase
{
    public function testMetadataIdentifiesTheConfigurationPanel(): void
    {
        $panel = new ConfigPanel();

        self::assertSame('config', $panel->id(), 'Stable ID must pair with the configuration collector.');
        self::assertSame('config', $panel->icon(), 'Icon must use the shared configuration glyph.');
        self::assertSame('Configuration', $panel->name(), 'Label must match the Yii2 panel.');
    }

    public function testRenderShowsRuntimeReadoutsAndSortedExtensionRoster(): void
    {
        $payload = ConfigSnapshot::capture(
            [
                'phpVersion' => '8.9.9-test',
                'yiiVersion' => '3-test',
                'application' => ['yii' => '3-test', 'name' => 'Test App', 'charset' => 'UTF-8'],
                'php' => ['version' => '8.9.9-test', 'xdebug' => false],
                'extensions' => [
                    'yiisoft/view' => ['name' => 'yiisoft/view', 'version' => '12.0'],
                    'yiisoft/assets' => ['name' => 'yiisoft/assets', 'version' => '5.1'],
                ],
            ],
        )->jsonSerialize();

        $html = (new ConfigPanel('/debug/'))->render($payload);

        self::assertStringContainsString('8.9.9-test', $html, 'PHP version readout must be rendered.');
        self::assertStringContainsString('Test App', $html, 'Application name must be rendered.');
        self::assertStringContainsString('yiisoft/assets', $html, 'Extension roster must list packages.');
        self::assertStringContainsString('/debug/php-info', $html, 'CTA must link to the phpinfo endpoint.');
        self::assertLessThan(
            strpos($html, 'yiisoft/view'),
            strpos($html, 'yiisoft/assets'),
            'Roster must be sorted alphabetically.',
        );
    }

    public function testToolbarItemsStayEmptySoBrandChipsOwnTheConfiguration(): void
    {
        self::assertSame([], (new ConfigPanel())->toolbarItems(['data' => []]), 'Panel must not emit a chip.');
    }
}
