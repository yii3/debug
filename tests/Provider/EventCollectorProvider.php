<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Provider;

use Psr\Http\Server\MiddlewareInterface;
use Yii3\Debug\Tests\Support\Stubs\AnonymousMiddlewareStubFactory;

/**
 * Action-wrapper metadata cases for {@see \Yii3\Debug\Tests\Collector\EventCollectorTest}.
 */
final class EventCollectorProvider
{
    /**
     * Provides anonymous middleware whose debug metadata never resolves to a named, public action.
     *
     * Cases naming a class use this provider because the source guard rejects classes that are not already loaded.
     *
     * @return iterable<string, array{MiddlewareInterface}>
     */
    public static function unsupportedActionWrappers(): iterable
    {
        yield 'absent debug metadata' => [
            AnonymousMiddlewareStubFactory::createWithoutDebugInfoArray(),
        ];
        yield 'callback naming an absent method' => [
            AnonymousMiddlewareStubFactory::create(['callback' => [self::class, 'missingAction']]),
        ];
        yield 'callback with an empty method' => [
            AnonymousMiddlewareStubFactory::create(['callback' => [self::class, '']]),
        ];
        yield 'callback with an extra element' => [
            AnonymousMiddlewareStubFactory::create(
                ['callback' => [self::class, 'unsupportedActionWrappers', 'extra']],
            ),
        ];
        yield 'scalar callback' => [
            AnonymousMiddlewareStubFactory::create(['callback' => 'App\\Action::run']),
        ];
    }
}
