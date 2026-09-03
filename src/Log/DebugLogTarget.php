<?php

declare(strict_types=1);

namespace Yii3\Debug\Log;

use Yiisoft\Log\{Message, Target};

use function array_values;

/**
 * Accumulates Yiisoft log messages in memory for the current request.
 */
final class DebugLogTarget extends Target
{
    /**
     * @var list<Message>
     */
    private array $captured = [];

    public function __construct()
    {
        parent::__construct();

        $this->setExportInterval(1);
    }

    /**
     * @return list<Message> Messages accumulated in emission order.
     */
    public function messages(): array
    {
        return $this->captured;
    }

    /**
     * Clears all messages accumulated for the previous request.
     */
    public function reset(): void
    {
        $this->collect([], true);
        $this->captured = [];
    }

    protected function export(): void
    {
        $this->captured = [
            ...$this->captured,
            ...array_values($this->getMessages()),
        ];
    }
}
