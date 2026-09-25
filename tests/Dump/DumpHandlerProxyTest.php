<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Dump;

use PHPForge\Debug\Storage\Json;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii3\Debug\Collector\DumpCollector;
use Yii3\Debug\Dump\DumpHandlerProxy;
use Yii3\Debug\Tests\Support\{Captured, TemporaryDirectory};
use Yii3\Debug\Tests\Support\Stubs\RecordingDumpHandlerStub;
use Yiisoft\VarDumper\{HandlerInterface, VarDumper};

use function array_map;
use function d;
use function file_put_contents;
use function mb_check_encoding;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

/**
 * Unit tests for {@see DumpHandlerProxy} recording, forwarding, and call-site detection of `yiisoft/var-dumper` dumps.
 */
final class DumpHandlerProxyTest extends TestCase
{
    private HandlerInterface|null $originalHandler = null;
    private string $root = '';

    #[RequiresOperatingSystemFamily('Linux')]
    public function testCallSiteFileIsMadeValidUtf8(): void
    {
        $this->root = TemporaryDirectory::create('yii3-debug-dump-');

        $file = $this->root . DIRECTORY_SEPARATOR . "caller-\xB1.php";

        file_put_contents($file, '<?php \Yiisoft\VarDumper\VarDumper::dump(1, 10, false);');

        $collector = $this->startedCollector(new RecordingDumpHandlerStub());

        require $file;

        $frameFile = self::trace($collector)[0]['file'] ?? null;

        self::assertSame(
            Json::safeString($file),
            $frameFile,
            'Path must be stored in its JSON-safe form.',
        );
        self::assertTrue(
            mb_check_encoding($frameFile, 'UTF-8'),
            'Stored path must be valid UTF-8.',
        );
    }

    public function testCallSiteSkipsInternalFramesWithoutAFile(): void
    {
        $collector = $this->startedCollector(new RecordingDumpHandlerStub());

        array_map(VarDumper::dump(...), ['value']);

        self::assertSame(
            [['file' => __FILE__, 'line' => __LINE__ - 3, 'function' => 'array_map']],
            self::trace($collector),
            'Call site must be the array_map() line, past the file-less frame.',
        );
    }

    public function testCallSiteSkipsTheVarDumperHelperFunctions(): void
    {
        $collector = $this->startedCollector(new RecordingDumpHandlerStub());

        $this->expectOutputString(PHP_EOL);

        d('value');

        self::assertSame(
            [['file' => __FILE__, 'line' => __LINE__ - 3, 'function' => 'd']],
            self::trace($collector),
            'Call site must be the d() line, not the helper file.',
        );
    }

    public function testHandleRecordsBeforeTheDecoratedHandlerThrows(): void
    {
        $collector = new DumpCollector();

        $collector->startup();

        $proxy = new DumpHandlerProxy(
            new class implements HandlerInterface {
                public function handle(mixed $variable, int $depth, bool $highlight = false): void
                {
                    throw new RuntimeException('Output failed.');
                }
            },
            $collector,
        );

        try {
            $proxy->handle('value', 10, false);
        } catch (RuntimeException) {
        }

        self::assertCount(
            1,
            Captured::dump($collector)?->entries() ?? [],
            'Dump must be recorded although the output failed.',
        );
    }

    public function testHandleRecordsTheCallSiteAndForwardsTheDump(): void
    {
        $previous = new RecordingDumpHandlerStub();

        $collector = $this->startedCollector($previous);

        VarDumper::dump(42, 5, false);

        self::assertSame(
            [
                [
                    'file' => __FILE__,
                    'line' => __LINE__ - 6,
                    'function' => 'dump',
                    'class' => VarDumper::class,
                    'type' => '::',
                ],
            ],
            self::trace($collector),
            'Call site must be the VarDumper::dump() line.',
        );
        self::assertSame(
            [[42, 5, false]],
            $previous->calls,
            'Dump must reach the decorated handler unchanged.',
        );
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

        TemporaryDirectory::remove($this->root);
    }

    private function startedCollector(HandlerInterface $previous): DumpCollector
    {
        VarDumper::setDefaultHandler($previous);

        $collector = new DumpCollector();

        $collector->startup();

        return $collector;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function trace(DumpCollector $collector): array
    {
        $rows = Captured::dump($collector)?->entries() ?? [];

        return ($rows[0] ?? null)->trace ?? [];
    }
}
