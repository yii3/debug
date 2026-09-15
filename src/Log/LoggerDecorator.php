<?php

declare(strict_types=1);

namespace Yii3\Debug\Log;

use Psr\Log\LoggerInterface;
use Yiisoft\Log\Logger;

/**
 * Adds the debugger log target to the application logger without redefining the logger service.
 */
final readonly class LoggerDecorator
{
    /**
     * Returns a logger writing to the debugger target as well.
     *
     * {@see Logger} is final and exposes no target mutator, so the logger is rebuilt from the targets it already
     * carries. The rebuilt instance restores the default flush interval and the default context provider; an
     * application that depends on either must configure it where the logger service is defined, not through
     * `setFlushInterval()` on the instance the container returns.
     *
     * A logger that is not a {@see Logger}, and one already writing to the target, is returned unchanged, so applying
     * the decoration twice is a no-op.
     *
     * @param LoggerInterface $logger Application logger to decorate.
     * @param DebugLogTarget $target Target accumulating the messages of the captured request.
     *
     * @return LoggerInterface Rebuilt logger, or the logger unchanged when it cannot or need not be decorated.
     */
    public static function decorate(LoggerInterface $logger, DebugLogTarget $target): LoggerInterface
    {
        if (!$logger instanceof Logger) {
            return $logger;
        }

        $targets = $logger->getTargets();

        foreach ($targets as $existing) {
            if ($existing === $target) {
                return $logger;
            }
        }

        return new Logger([...$targets, $target]);
    }
}
