<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Panel\Event\{EventCapture, EventInspection, EventRow, EventSnapshot};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use ReflectionClass;
use Throwable;
use UnexpectedValueException;
use WeakMap;
use Yii3\Debug\Exception\Message;

use function array_is_list;
use function class_exists;
use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function microtime;
use function strpos;
use function substr;

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
     * Opt-in capture of whitelisted lifecycle context. Arbitrary event properties remain unread.
     */
    public bool $captureContext = false;
    /**
     * Opt-in argument-free trace depth; zero disables capture, and sixteen is the hard maximum.
     */
    public int $traceLimit = 0;

    private int $depth = 0;
    /**
     * @var list<EventRow>
     */
    private array $events = [];
    private int $nextScope = 0;
    /**
     * @var WeakMap<object, list<array{id: int, depth: int}>>|null Transient middleware identity, never persisted.
     */
    private WeakMap|null $scopes = null;
    private bool $started = false;

    /**
     * @return array<string, mixed>|null Encoded Events panel payload; `null` when the collector never started.
     */
    public function capture(): array|null
    {
        if ($this->started === false) {
            return null;
        }

        return (new EventSnapshot($this->events))->jsonSerialize();
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

        $class = self::normalizeClassLabel($event::class);
        $source = self::normalizeClassLabel(self::source($event, $senderClass));

        $row = new EventRow(
            time: microtime(true),
            name: $class,
            class: $class,
            isStatic: '0',
            senderClass: $source,
        );

        $this->events[] = in_array($event::class, self::MIDDLEWARE_EVENTS, true)
            || $this->captureContext || $this->traceLimit > 0
            ? $row->withInspection($this->inspect($event))
            : $row;
    }

    public function shutdown(): void
    {
        $this->started = false;
        $this->events = [];
        $this->scopes = null;
        $this->nextScope = 0;
        $this->depth = 0;
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
     * Correlates only known lifecycle markers by object identity and per-object nesting, never by class name.
     */
    private function inspect(object $event): EventInspection
    {
        $context = [];
        $trace = [];

        $contextStatus = $this->captureContext ? 'unsupported' : 'disabled';
        $traceStatus = $this->traceLimit > 0 ? 'captured' : 'disabled';

        $pairId = null;
        $phase = '';
        $depth = 0;

        $clock = hrtime(true) / 1_000_000_000;

        if (
            in_array($event::class, self::MIDDLEWARE_EVENTS, true)
            && method_exists($event, 'getMiddleware')
        ) {
            $middleware = $event->getMiddleware();

            if (is_object($middleware)) {
                $this->scopes ??= new WeakMap();
                $stack = $this->scopes[$middleware] ?? [];
                $phase = $event::class === self::MIDDLEWARE_EVENTS[0] ? 'enter' : 'leave';

                if ($phase === 'enter') {
                    $pairId = ++$this->nextScope;
                    $depth = $this->depth++;
                    $stack[] = ['id' => $pairId, 'depth' => $depth];
                } else {
                    $scope = array_pop($stack);

                    $pairId = $scope['id'] ?? null;
                    $depth = $scope['depth'] ?? 0;

                    if ($scope !== null) {
                        $this->depth = max(0, $this->depth - 1);
                    }
                }

                $this->scopes[$middleware] = $stack;
            }

            if ($this->captureContext) {
                try {
                    if ($phase === 'enter' && method_exists($event, 'getRequest')) {
                        $request = $event->getRequest();

                        if (!$request instanceof ServerRequestInterface) {
                            throw new UnexpectedValueException(
                                Message::HTTP_REQUEST_EXPECTED->getMessage(),
                            );
                        }

                        $context = EventCapture::context(
                            [
                                'Request method' => $request->getMethod(),
                                'Request path (no query)' => $request->getUri()->getPath(),
                            ],
                        );
                    } elseif ($phase === 'leave' && method_exists($event, 'getResponse')) {
                        $response = $event->getResponse();

                        if ($response !== null && !$response instanceof ResponseInterface) {
                            throw new UnexpectedValueException(
                                Message::HTTP_RESPONSE_EXPECTED->getMessage(),
                            );
                        }

                        $context = EventCapture::context(
                            [
                                'Response observed' => $response === null
                                    ? 'No response observed'
                                    : (string) $response->getStatusCode(),
                            ],
                        );
                    }

                    $contextStatus = 'captured';
                } catch (Throwable) {
                    $contextStatus = 'failed';
                }
            }
        }

        if ($this->traceLimit > 0) {
            try {
                $trace = EventCapture::trace(
                    debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32),
                    $this->traceLimit,
                    [__FILE__, dirname(__DIR__) . '/Event/DebugEventDispatcher.php'],
                );
            } catch (Throwable) {
                $traceStatus = 'failed';
            }
        }

        return (new EventInspection())
            ->withContext($context, $contextStatus)
            ->withTrace($trace, $traceStatus)
            ->withLifecycle($pairId, $phase, $depth, $clock);
    }

    /**
     * Resolves Yii middleware-factory action wrappers to their whitelisted class and method name.
     */
    private static function middlewareSource(object $middleware): string
    {
        if (
            !(new ReflectionClass($middleware))->isAnonymous()
            || !method_exists($middleware, '__debugInfo')
        ) {
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
     * Removes PHP's NUL-delimited source suffix from anonymous class labels.
     */
    private static function normalizeClassLabel(string $label): string
    {
        $separator = strpos($label, "\0");

        return $separator === false ? $label : substr($label, 0, $separator);
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
