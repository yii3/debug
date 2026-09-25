<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Dump\DumpRow;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Collector\DumpCollector;
use Yii3\Debug\Dump\DumpHandlerProxy;
use Yii3\Debug\Tests\Support\Captured;
use Yii3\Debug\Tests\Support\Stubs\RecordingDumpHandlerStub;
use Yiisoft\VarDumper\{HandlerInterface, VarDumper};

use function mb_check_encoding;
use function microtime;
use function ob_get_level;

/**
 * Unit tests for {@see DumpCollector} lifecycle around the `yiisoft/var-dumper` default handler and the rendering of
 * each captured dump.
 */
final class DumpCollectorTest extends TestCase
{
    private HandlerInterface|null $originalHandler = null;

    public function testCaptureIsNullOutsideTheCaptureWindow(): void
    {
        $collector = new DumpCollector();

        self::assertNull(
            $collector->capture(),
            'Idle collector must record nothing.',
        );

        $collector->startup();

        self::assertSame(
            ['entries' => []],
            $collector->capture(),
            'Started collector must report an empty capture.',
        );

        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Stopped collector must record nothing.',
        );
    }

    public function testCollectKeepsTheMessageValidUtf8(): void
    {
        VarDumper::setDefaultHandler(new RecordingDumpHandlerStub());

        $collector = new DumpCollector();

        $collector->startup();

        VarDumper::dump("a\xB1b", 10, false);
        VarDumper::dump("a\xB1b", 10, true);

        $plain = self::entry($collector, 0);
        $highlighted = self::entry($collector, 1);

        self::assertSame(
            "'a\u{FFFD}b'",
            $plain->message,
            'Plain dump must substitute the invalid byte.',
        );
        self::assertTrue(
            mb_check_encoding($highlighted->message, 'UTF-8'),
            'Highlighted dump must be valid UTF-8.',
        );
    }

    public function testCollectRendersWithTheRequestedDepthAndHighlight(): void
    {
        VarDumper::setDefaultHandler(new RecordingDumpHandlerStub());

        $collector = new DumpCollector();

        $collector->startup();

        $value = ['user' => ['name' => '<b>Ada</b>']];

        $before = microtime(true) * 1000;

        VarDumper::dump($value, 1, false);
        VarDumper::dump($value, 10, true);

        $after = microtime(true) * 1000;

        $plain = self::entry($collector, 0);
        $highlighted = self::entry($collector, 1);

        self::assertSame(
            "[\n    'user' =&gt; [...]\n]",
            $plain->message,
            'Plain dump must stop at the requested depth and be HTML-encoded.',
        );
        self::assertSame(
            <<<HTML
            <pre><code style="color: #000000"><span style="color: #007700">[
                </span><span style="color: #DD0000">'user' </span><span style="color: #007700">=&gt; [
                    </span><span style="color: #DD0000">'name' </span><span style="color: #007700">=&gt; </span><span style="color: #DD0000">'&lt;b&gt;Ada&lt;/b&gt;'
                </span><span style="color: #007700">]
            ]</span></code></pre>
            HTML,
            $highlighted->message,
            'Highlighted dump must match the var-dumper echo output.',
        );
        self::assertSame(
            LogLevel::TRACE,
            $plain->level,
            'Dumps must be recorded at trace level, as in Yii2.',
        );
        self::assertSame(
            'application',
            $plain->category,
            "Dumps must use Yii2's default category.",
        );
        self::assertGreaterThanOrEqual(
            $before,
            $plain->time,
            'Time must be the capture moment in milliseconds.',
        );
        self::assertLessThanOrEqual(
            $after,
            $plain->time,
            'Time must be the capture moment in milliseconds.',
        );
    }

    public function testDumpsOutsideTheCaptureWindowAreForwardedButNotRecorded(): void
    {
        $previous = new RecordingDumpHandlerStub();

        VarDumper::setDefaultHandler($previous);

        $collector = new DumpCollector();

        (new DumpHandlerProxy($previous, $collector))->handle('before', 10);

        $collector->startup();

        VarDumper::dump('during', 10, false);

        self::assertSame(
            ["'during'"],
            self::messages($collector),
            'Only the dump inside the window may be recorded.',
        );

        $proxy = VarDumper::getDefaultHandler();

        $collector->shutdown();

        $proxy->handle('after', 10, false);

        $collector->startup();

        self::assertSame(
            [],
            self::messages($collector),
            'Shutdown must clear the dumps and ignore later ones.',
        );
        self::assertSame(
            [['before', 10, false], ['during', 10, false], ['after', 10, false]],
            $previous->calls,
            'Every dump must still reach the decorated handler.',
        );
    }

    public function testShutdownKeepsAHandlerInstalledDuringTheRequest(): void
    {
        $collector = new DumpCollector();

        $collector->startup();

        $replacement = new RecordingDumpHandlerStub();

        VarDumper::setDefaultHandler($replacement);

        $collector->shutdown();

        self::assertSame(
            $replacement,
            VarDumper::getDefaultHandler(),
            'A handler installed after startup must not be replaced.',
        );
    }

    public function testStartupDecoratesTheDefaultHandlerOnceAndShutdownRestoresIt(): void
    {
        $previous = new RecordingDumpHandlerStub();

        VarDumper::setDefaultHandler($previous);

        $collector = new DumpCollector();

        $collector->startup();

        $proxy = VarDumper::getDefaultHandler();

        self::assertInstanceOf(
            DumpHandlerProxy::class,
            $proxy,
            'Startup must install the proxy.',
        );
        self::assertSame(
            $previous,
            $proxy->handler(),
            'Proxy must decorate the previous default handler.',
        );

        $collector->startup();

        self::assertSame(
            $proxy,
            VarDumper::getDefaultHandler(),
            'A second startup must not wrap the proxy again.',
        );

        $collector->shutdown();

        self::assertSame(
            $previous,
            VarDumper::getDefaultHandler(),
            'Shutdown must restore the previous handler.',
        );
    }

    public function testThrowRuntimeExceptionWhenRenderingFailsAfterClosingTheBuffer(): void
    {
        VarDumper::setDefaultHandler(new RecordingDumpHandlerStub());

        $collector = new DumpCollector();

        $collector->startup();

        $level = ob_get_level();

        $this->expectException(RuntimeException::class);

        try {
            VarDumper::dump(
                new class {
                    public function __debugInfo(): array
                    {
                        throw new RuntimeException('Not dumpable.');
                    }
                },
                10,
                true,
            );
        } finally {
            self::assertSame(
                $level,
                ob_get_level(),
                'Rendering buffer must be closed.',
            );
        }
    }

    protected function setUp(): void
    {
        $this->originalHandler = VarDumper::getDefaultHandler();
    }

    protected function tearDown(): void
    {
        if ($this->originalHandler !== null) {
            VarDumper::setDefaultHandler($this->originalHandler);
        }
    }

    /**
     * @return list<DumpRow>
     */
    private static function entries(DumpCollector $collector): array
    {
        return Captured::dump($collector)?->entries() ?? [];
    }

    private static function entry(DumpCollector $collector, int $index): DumpRow
    {
        return self::entries($collector)[$index] ?? self::fail("Expected dump #{$index}.");
    }

    /**
     * @return list<string>
     */
    private static function messages(DumpCollector $collector): array
    {
        $messages = [];

        foreach (self::entries($collector) as $row) {
            $messages[] = $row->message;
        }

        return $messages;
    }
}
