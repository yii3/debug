<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Yiisoft\VarDumper\HandlerInterface;

/**
 * Records every dump it handles as a variable, depth, and highlight triple instead of printing it.
 */
final class RecordingDumpHandlerStub implements HandlerInterface
{
    /**
     * @var list<array{mixed, int, bool}>
     */
    public array $calls = [];

    public function handle(mixed $variable, int $depth, bool $highlight = false): void
    {
        $this->calls[] = [$variable, $depth, $highlight];
    }
}
