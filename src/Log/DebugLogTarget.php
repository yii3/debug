<?php

declare(strict_types=1);

namespace Yii3\Debug\Log;

use Yiisoft\Log\{Message, Target};

use function array_values;

/**
 * Accumulates every Yii3 log message in memory, so the debug Log collector can snapshot the full request log.
 *
 * Usage example:
 *
 * ```php
 * $target = new \Yii3\Debug\Log\DebugLogTarget();
 * $logger = new \Yiisoft\Log\Logger([$target]);
 * ```
 */
final class DebugLogTarget extends Target
{
    /**
     * @var list<Message> Messages accumulated for the current request, in emission order.
     */
    private array $captured = [];

    public function __construct()
    {
        parent::__construct();

        $this->setExportInterval(1);
    }

    /**
     * Returns the accumulated log messages.
     *
     * Usage example:
     *
     * ```php
     * $messages = $target->messages();
     * ```
     *
     * @return list<Message> Messages in emission order.
     */
    public function messages(): array
    {
        return $this->captured;
    }

    /**
     * Clears the accumulated messages, so a reused worker process starts clean.
     */
    public function reset(): void
    {
        $this->captured = [];
    }

    /**
     * Moves the collected batch into the in-memory accumulator.
     */
    protected function export(): void
    {
        $this->captured = [...$this->captured, ...array_values($this->getMessages())];
    }
}
