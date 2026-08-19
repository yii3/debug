<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Helper\{Dump, LogLevel};
use PHPForge\Debug\Panel\Dump\DumpSnapshot;

use function array_slice;
use function debug_backtrace;
use function highlight_string;
use function htmlspecialchars;
use function microtime;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Captures values explicitly submitted by application code for the Dump panel.
 */
final class DumpCollector implements CollectorInterface
{
    private bool $active = false;

    /**
     * @var list<array<int, mixed>> Captured logger-compatible tuples in declaration order.
     */
    private array $messages = [];

    public function __construct(
        private readonly int $depth = 10,
        private readonly bool $highlight = true,
    ) {}

    public function capture(): DumpSnapshot|null
    {
        return $this->active ? DumpSnapshot::capture($this->messages) : null;
    }

    /**
     * Captures one value together with its category and application call site.
     */
    public function collect(mixed $value, string $category = 'application'): void
    {
        if (!$this->active) {
            return;
        }

        $dump = Dump::asString($value, $this->depth);

        $message = $this->highlight
            ? highlight_string("<?php {$dump}", true)
            : htmlspecialchars($dump, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1);

        $this->messages[] = [$message, LogLevel::TRACE, $category, microtime(true), $trace];
    }

    public function id(): string
    {
        return 'dump';
    }

    public function shutdown(): void
    {
        $this->active = false;
        $this->messages = [];
    }

    public function startup(): void
    {
        $this->active = true;
        $this->messages = [];
    }
}
