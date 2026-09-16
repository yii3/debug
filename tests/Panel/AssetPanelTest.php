<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\AssetPanel;

/**
 * Unit tests for {@see AssetPanel} adapting the PHPForge Asset Bundles panel to the Yii3 debugger.
 */
final class AssetPanelTest extends TestCase
{
    public function testPanelIdentityMatchesAssetCollector(): void
    {
        $panel = new AssetPanel();

        self::assertSame(
            'asset',
            $panel->id(),
            'Panel ID must match the collector ID.',
        );
        self::assertSame(
            'Asset Bundles',
            $panel->name(),
            'Panel title must match the PHPForge portable panel.',
        );
        self::assertSame(
            'asset',
            $panel->icon(),
            'Icon must match the portable panel declaration.',
        );
    }
}
