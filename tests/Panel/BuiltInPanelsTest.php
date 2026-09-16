<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use PHPUnit\Framework\TestCase;
use Yii3\Debug\Panel\BuiltInPanels;

/**
 * Unit tests for {@see BuiltInPanels} classifying the panel IDs shared by the sidebar and the toolbar.
 */
final class BuiltInPanelsTest extends TestCase
{
    public function testIsBuiltInAcceptsEveryDeclaredIdentifier(): void
    {
        self::assertTrue(
            BuiltInPanels::isBuiltIn('request'),
            'The Request panel must be built-in.',
        );
        self::assertTrue(
            BuiltInPanels::isBuiltIn('log'),
            'The Logs panel must be built-in.',
        );
        self::assertTrue(
            BuiltInPanels::isBuiltIn('event'),
            'The Events panel must be built-in.',
        );
        self::assertTrue(
            BuiltInPanels::isBuiltIn('profiling'),
            'The Profiling panel must be built-in.',
        );
        self::assertTrue(
            BuiltInPanels::isBuiltIn('db'),
            'The Database panel must be built-in.',
        );
        self::assertTrue(
            BuiltInPanels::isBuiltIn('asset'),
            'The Asset Bundles panel must be built-in.',
        );
    }

    public function testIsBuiltInRejectsIdentifiersOutsideTheList(): void
    {
        self::assertFalse(
            BuiltInPanels::isBuiltIn('inertia'),
            'An extension ID must not be built-in.',
        );
        self::assertFalse(
            BuiltInPanels::isBuiltIn(''),
            'An empty ID must not be built-in.',
        );
        self::assertFalse(
            BuiltInPanels::isBuiltIn('Request'),
            'Matching must stay case-sensitive.',
        );
    }
}
