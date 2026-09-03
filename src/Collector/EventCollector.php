<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Panel\Event\{EventRow, EventSnapshot};
use ReflectionClass;
use Throwable;

use function array_is_list;
use function class_exists;
use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function microtime;

/**
 * Captures PSR-14 event metadata for the current request.
 */
final class EventCollector implements CollectorInterface
{
    /**
     * Framework lifecycle events whose subject class is safe to retain as source metadata.
     */
    private const array MIDDLEWARE_EVENTS = [
        'Yiisoft\\Middleware\\Dispatcher\\Event\\BeforeMiddleware',
        'Yiisoft\\Middleware\\Dispatcher\\Event\\AfterMiddleware',
    ];

    /**
     * @var list<EventRow>
     */
    private array $events = [];
    private bool $started = false;

    public function capture(): EventSnapshot|null
    {
        if ($this->started === false) {
            return null;
        }

        return new EventSnapshot($this->events);
    }

    public function id(): string
    {
        return 'event';
    }

    /**
     * Records only dispatch metadata while collection is active.
     *
     * @param string $senderClass Immediate class that invoked the decorated dispatcher, used as a fallback source.
     */
    public function record(object $event, string $senderClass = ''): void
    {
        if ($this->started === false) {
            return;
        }

        $class = $event::class;

        $source = self::source($event, $senderClass);

        $this->events[] = new EventRow(
            time: microtime(true),
            name: $class,
            class: $class,
            isStatic: '0',
            senderClass: $source,
        );
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->events = [];
    }

    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->events = [];
        $this->started = true;
    }

    /**
     * Resolves Yii middleware-factory action wrappers to their whitelisted class and method name.
     */
    private static function middlewareSource(object $middleware): string
    {
        if (!(new ReflectionClass($middleware))->isAnonymous() || !method_exists($middleware, '__debugInfo')) {
            return $middleware::class;
        }

        try {
            $debugInfo = $middleware->__debugInfo();
        } catch (Throwable) {
            return $middleware::class;
        }

        if (!is_array($debugInfo)) {
            return $middleware::class;
        }

        return self::wrappedActionName($debugInfo['callback'] ?? null) ?? $middleware::class;
    }

    /**
     * Resolves the most useful source without retaining an event subject or its payload.
     */
    private static function source(object $event, string $callerClass): string
    {
        if (
            in_array($event::class, self::MIDDLEWARE_EVENTS, true)
            && method_exists($event, 'getMiddleware')
        ) {
            $middleware = $event->getMiddleware();

            if (is_object($middleware)) {
                return self::middlewareSource($middleware);
            }
        }

        return $callerClass;
    }

    /**
     * Accepts only the named, public action shape emitted by Yii's middleware factory.
     */
    private static function wrappedActionName(mixed $callback): string|null
    {
        if (!is_array($callback) || !array_is_list($callback) || count($callback) !== 2) {
            return null;
        }

        [$class, $method] = $callback;

        if (!is_string($class) || !is_string($method) || $class === '' || $method === '') {
            return null;
        }

        if (!class_exists($class, false)) {
            return null;
        }

        $action = new ReflectionClass($class);

        if ($action->isAnonymous() || !$action->hasMethod($method) || !$action->getMethod($method)->isPublic()) {
            return null;
        }

        return "{$class}::{$method}";
    }
}
