<?php

declare(strict_types=1);

namespace Yii3\Debug\Integration;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use Throwable;
use Yii3\Debug\Collector\MailCollector;
use Yiisoft\Mailer\{MailerInterface, MessageInterface, SendResults};

use function array_fill_keys;
use function array_map;
use function spl_object_id;

/**
 * Decorates a Yii3 mailer and reports definitive send outcomes to the debug collector.
 */
final readonly class MailerInterfaceProxy implements MailerInterface
{
    private InstrumentationGuard $guard;

    public function __construct(
        private MailerInterface $decorated,
        private MailCollector $collector,
        InstrumentationGuard|null $guard = null,
    ) {
        $this->guard = $guard ?? new InstrumentationGuard();
    }

    public function send(MessageInterface $message): void
    {
        try {
            $this->decorated->send($message);
        } catch (Throwable $throwable) {
            $this->collectMessage($message, false);

            throw $throwable;
        }

        $this->collectMessage($message, true);
    }

    public function sendMultiple(array $messages): SendResults
    {
        try {
            $results = $this->decorated->sendMultiple($messages);
        } catch (Throwable $throwable) {
            foreach ($messages as $message) {
                $this->collectMessage($message, false);
            }

            throw $throwable;
        }

        $successful = array_fill_keys(array_map(spl_object_id(...), $results->successMessages), true);

        foreach ($messages as $message) {
            $this->collectMessage($message, isset($successful[spl_object_id($message)]));
        }

        return $results;
    }

    /**
     * Reports one mail outcome without allowing collector failures to alter mailer behavior.
     */
    private function collectMessage(MessageInterface $message, bool $successful): void
    {
        $this->guard->observe(fn() => $this->collector->collectMessage($message, $successful));
    }
}
