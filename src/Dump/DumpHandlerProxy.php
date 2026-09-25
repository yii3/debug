<?php

declare(strict_types=1);

namespace Yii3\Debug\Dump;

use PHPForge\Debug\Storage\Json;
use ReflectionClass;
use Yii3\Debug\Collector\DumpCollector;
use Yiisoft\VarDumper\{HandlerInterface, VarDumper};

use function array_filter;
use function array_replace;
use function debug_backtrace;
use function dirname;
use function reset;
use function str_starts_with;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Intercepts `yiisoft/var-dumper` dumps to feed the `DumpCollector`, then forwards each dump unchanged.
 *
 * {@see DumpCollector::startup()} installs the proxy as the var-dumper default handler, so `VarDumper::dump()` and the
 * `yiisoft/var-dumper` helpers `d()`, `dump()`, and `dd()` pass through it while the application output stays exactly
 * what the decorated handler produces. When `symfony/var-dumper` is loaded first, as Codeception and PsySH do, its own
 * global `dump()` and `dd()` are defined instead and never reach this proxy.
 *
 * @phpstan-import-type TraceFrame from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
final readonly class DumpHandlerProxy implements HandlerInterface
{
    /**
     * @param HandlerInterface $handler Handler that was the default before the proxy, receiving every dump.
     * @param DumpCollector $collector Collector recording the dumps of the request.
     */
    public function __construct(private HandlerInterface $handler, private DumpCollector $collector) {}

    /**
     * Records the dump with its call site, then forwards it to the decorated handler.
     *
     * Recording comes first, so the dump is already in the collector when the decorated handler throws, or when `dd()`
     * ends the script right after the output and {@see \Yii3\Debug\Capture\DeferredCapture} writes the capture
     * armed for the request at shutdown.
     *
     * @param mixed $variable Dumped value.
     * @param int $depth Maximum nesting depth requested by the caller.
     * @param bool $highlight Whether the caller requested syntax-highlighted output.
     */
    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $callSite = self::callSite();

        $this->collector->collect($variable, $depth, $highlight, $callSite === null ? [] : [$callSite]);

        $this->handler->handle($variable, $depth, $highlight);
    }

    /**
     * Returns the handler the proxy decorates.
     *
     * @return HandlerInterface Handler receiving every forwarded dump.
     */
    public function handler(): HandlerInterface
    {
        return $this->handler;
    }

    /**
     * Returns the application frame that requested the dump, in the backtrace frame shape of the Yii2 capture.
     *
     * The first frame whose file lies outside this proxy and outside `yiisoft/var-dumper` names the line that called
     * `VarDumper::dump()`, `d()`, or `dump()`; the frames of the var-dumper helpers and handlers in between, and those
     * of internal callers that carry no file, are skipped. The file path comes from the filesystem, so it is made valid
     * UTF-8 before it reaches the JSON capture.
     *
     * @return TraceFrame|null The call-site frame (`file`, `line`, `function`, and `class`/`type` for a method call),
     * or `null` when no frame qualifies.
     */
    private static function callSite(): array|null
    {
        $varDumperDirectory = dirname((string) (new ReflectionClass(VarDumper::class))->getFileName());

        $frames = array_filter(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            static fn(array $frame): bool => isset($frame['file'])
                && $frame['file'] !== __FILE__
                && !str_starts_with($frame['file'], $varDumperDirectory),
        );

        $frame = reset($frames);

        return $frame === false ? null : array_replace($frame, ['file' => Json::safeString($frame['file'])]);
    }
}
