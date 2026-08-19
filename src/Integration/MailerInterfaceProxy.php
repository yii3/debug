<?php

declare(strict_types=1);

namespace Yii3\Debug\Integration;

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
    public function __construct(
        private MailerInterface $decorated,
        private MailCollector $collector,
    ) {}

    public function send(MessageInterface $message): void
    {
        try {
            $this->decorated->send($message);
        } catch (Throwable $throwable) {
            $this->collector->collectMessage($message, false);

            throw $throwable;
        }

        $this->collector->collectMessage($message, true);
    }

    public function sendMultiple(array $messages): SendResults
    {
        try {
            $results = $this->decorated->sendMultiple($messages);
        } catch (Throwable $throwable) {
            foreach ($messages as $message) {
                $this->collector->collectMessage($message, false);
            }

            throw $throwable;
        }

        $successful = array_fill_keys(array_map(spl_object_id(...), $results->successMessages), true);

        foreach ($messages as $message) {
            $this->collector->collectMessage($message, isset($successful[spl_object_id($message)]));
        }

        return $results;
    }
}
