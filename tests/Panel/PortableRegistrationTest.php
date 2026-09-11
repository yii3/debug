<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Panel;

use InvalidArgumentException;
use PHPForge\Vite\Debug\VitePanel;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Tests\Support\Stubs\ExtensionCollectorStub;

/**
 * Unit tests for {@see ExtensionRegistry} rejecting portable collectors and panels registered twice.
 */
final class PortableRegistrationTest extends TestCase
{
    public function testThrowInvalidArgumentExceptionForDuplicatePortableCollectors(): void
    {
        $collector = new ExtensionCollectorStub();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate debug collector ID');

        ExtensionRegistry::create([$collector, $collector]);
    }

    public function testThrowInvalidArgumentExceptionForDuplicatePortablePanels(): void
    {
        $panel = new VitePanel();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate debug panel ID');

        ExtensionRegistry::create(panels: [$panel, $panel]);
    }
}
