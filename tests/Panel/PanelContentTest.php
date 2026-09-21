<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Panel\{ExtensionPanelInterface, PanelContent};

/**
 * Unit tests for {@see PanelContent} applying the fail-open content policy shared by the toolbar and the sidebar.
 */
final class PanelContentTest extends TestCase
{
    public function testIsPresentReturnsTheVerdictReportedByThePanel(): void
    {
        self::assertTrue(
            PanelContent::isPresent($this->panel(true), ['value' => true]),
            'A reported `true` must pass through.',
        );
        self::assertFalse(
            PanelContent::isPresent($this->panel(false), ['value' => true]),
            'A reported `false` must pass through.',
        );
    }

    public function testIsPresentReturnsTrueWhenThePanelThrows(): void
    {
        $panel = self::createStub(ExtensionPanelInterface::class);

        $panel
            ->method('hasContent')
            ->willThrowException(new RuntimeException('Unable to inspect panel content.'));

        self::assertTrue(
            PanelContent::isPresent($panel, ['value' => true]),
            'A failed verdict must stay discoverable.',
        );
    }

    /**
     * Creates a panel stub reporting a fixed content verdict.
     *
     * @param bool $hasContent Verdict the stub reports for any payload.
     *
     * @return ExtensionPanelInterface Panel stub.
     */
    private function panel(bool $hasContent): ExtensionPanelInterface
    {
        $panel = self::createStub(ExtensionPanelInterface::class);

        $panel
            ->method('hasContent')
            ->willReturn($hasContent);

        return $panel;
    }
}
