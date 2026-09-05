<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Routing;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Routing\HandlerDefinitionNormalizer;

use function explode;

/**
 * Unit tests for persistence-safe Yii handler labels.
 */
#[Group('request')]
#[Group('routing')]
final class HandlerDefinitionNormalizerTest extends TestCase
{
    public function testDescribeAllKeepsSupportedDefinitionsInExecutionOrder(): void
    {
        self::assertSame(
            [
                'App\Middleware\Authentication',
                'Closure',
                'App\Middleware\Authorization',
            ],
            HandlerDefinitionNormalizer::describeAll(
                [
                    'App\Middleware\Authentication',
                    static fn(): int => 1,
                    ['class' => 'App\Middleware\Authorization'],
                    42,
                ],
            ),
            'Middleware labels must remain ordered while unsupported definitions are omitted.',
        );
    }

    public function testDescribeRemovesAnonymousClassSourcePaths(): void
    {
        $handler = new class {};

        $description = HandlerDefinitionNormalizer::describe($handler);

        self::assertNotNull(
            $description,
            'An anonymous handler must retain a useful class label.',
        );
        self::assertStringNotContainsString(
            "\0",
            $description,
            'Anonymous handler labels must not retain PHP\'s NUL-delimited source suffix.',
        );
        self::assertStringNotContainsString(
            __FILE__,
            $description,
            'Anonymous handler labels must not expose their declaration path.',
        );
        self::assertSame(
            explode("\0", $handler::class, 2)[0],
            $description,
            'Removing the source suffix must retain the complete anonymous class label.',
        );
    }
}
