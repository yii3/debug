<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPForge\Debug\Storage\Json;
use Yii3\Debug\Dump\DumpHandlerProxy;
use Yiisoft\VarDumper\Handler\EchoHandler;
use Yiisoft\VarDumper\VarDumper;

use function htmlspecialchars;
use function microtime;
use function ob_get_clean;
use function ob_start;

use const ENT_HTML5;
use const ENT_SUBSTITUTE;

/**
 * Captures the values dumped through `yiisoft/var-dumper` during the request for the Dump panel.
 *
 * Yii3 has no `Yii::debug()`, so the capture point is the var-dumper default handler: {@see startup()} decorates it with
 * a {@see DumpHandlerProxy} and {@see shutdown()} puts it back. Each dump is recorded in the logger-tuple shape the
 * Yii2 collector produces (trace level, `application` category, call-site frame), so a capture written by either host
 * renders through the same {@see \PHPForge\Debug\Panel\Dump\DumpCardRenderer}.
 *
 * @phpstan-import-type LogTuple from \PHPForge\Debug\Panel\Log\LogSnapshot
 * @phpstan-import-type TraceFrame from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
final class DumpCollector implements CollectorInterface
{
    /**
     * Category recorded for every dump, the one `Yii::debug()` uses by default in Yii2.
     */
    private const string CATEGORY = 'application';
    /**
     * Captured dumps in call order, in the shape {@see DumpSnapshot::capture()} reads.
     *
     * @var list<LogTuple>
     */
    private array $dumps = [];
    /**
     * Proxy installed as the var-dumper default handler by {@see startup()}, or `null` while none is installed.
     */
    private DumpHandlerProxy|null $proxy = null;
    /**
     * Indicates whether collection is active for the current request lifecycle.
     */
    private bool $started = false;

    /**
     * Encodes the captured dumps into the Dump panel payload.
     *
     * @return array<string, mixed>|null Encoded Dump panel payload; `null` when the collector never started.
     */
    public function capture(): array|null
    {
        if (!$this->started) {
            return null;
        }

        return DumpSnapshot::capture($this->dumps)->jsonSerialize();
    }

    /**
     * Records one dump, pre-rendered the way the application output shows it.
     *
     * The value is rendered with the depth and highlighting the caller requested: highlighted output is the markup
     * var-dumper's {@see EchoHandler} prints, which the Dump card keeps; plain output is HTML-encoded, as the Yii2
     * collector does, because the card renders the message as markup.
     *
     * @param mixed $variable Dumped value.
     * @param int $depth Maximum nesting depth requested by the caller.
     * @param bool $highlight Whether the caller requested syntax-highlighted output.
     * @param list<TraceFrame> $trace Call-site frame of the dump, or an empty list when unknown.
     */
    public function collect(mixed $variable, int $depth, bool $highlight, array $trace): void
    {
        if (!$this->started) {
            return;
        }

        $this->dumps[] = [
            Json::safeString(self::render($variable, $depth, $highlight)),
            LogLevel::TRACE,
            self::CATEGORY,
            microtime(true),
            $trace,
        ];
    }

    /**
     * Returns the stable identifier of this collector.
     *
     * @return string Stable ID pairing this collector with its panel.
     */
    public function id(): string
    {
        return 'dump';
    }

    /**
     * Stops capturing, clears the recorded dumps, and restores the var-dumper default handler.
     *
     * The previous handler is restored only while the proxy is still the default, so a handler the application
     * installed during the request is kept; a proxy left in its chain forwards without recording.
     */
    public function shutdown(): void
    {
        $this->started = false;
        $this->dumps = [];

        if ($this->proxy !== null && VarDumper::getDefaultHandler() === $this->proxy) {
            VarDumper::setDefaultHandler($this->proxy->handler());
        }

        $this->proxy = null;
    }

    /**
     * Starts capturing by installing a {@see DumpHandlerProxy} around the current var-dumper default handler.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->proxy = new DumpHandlerProxy(VarDumper::getDefaultHandler(), $this);

        VarDumper::setDefaultHandler($this->proxy);

        $this->started = true;
    }

    /**
     * Renders a dumped value as the markup the Dump card displays.
     *
     * @param mixed $variable Dumped value.
     * @param int $depth Maximum nesting depth.
     * @param bool $highlight Whether to syntax-highlight the output.
     *
     * @return string Highlighted markup, or the HTML-encoded plain dump.
     */
    private static function render(mixed $variable, int $depth, bool $highlight): string
    {
        if (!$highlight) {
            return htmlspecialchars(
                VarDumper::create($variable)->asString($depth),
                ENT_HTML5 | ENT_SUBSTITUTE,
                'UTF-8',
            );
        }

        ob_start();

        try {
            (new EchoHandler())->handle($variable, $depth, true);
        } finally {
            $output = ob_get_clean();
        }

        return $output === false ? '' : $output;
    }
}
