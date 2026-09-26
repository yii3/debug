<?php

declare(strict_types=1);

use PHPForge\Debug\Collector\CollectorCoordinator;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mime\Email;
use Yii3\Debug\Collector\{
    AssetCollector,
    DbCollector,
    DumpCollector,
    EventCollector,
    LogCollector,
    MailCollector,
    ProfilingCollector,
    RequestCollector,
    UserCollector,
};
use Yii3\Debug\ExtensionRegistry;
use Yiisoft\Mailer\Event\AfterSend;
use Yiisoft\Mailer\MessageInterface;
use Yiisoft\Mailer\Symfony\EmailFactory;
use Yiisoft\User\CurrentUser;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

$mailerInstalled = class_exists(AfterSend::class);
$userInstalled = class_exists(CurrentUser::class);

return [
    CollectorCoordinator::class => static fn(
        RequestCollector $requestCollector,
        LogCollector $logCollector,
        EventCollector $eventCollector,
        ProfilingCollector $profilingCollector,
        DbCollector $dbCollector,
        DumpCollector $dumpCollector,
        AssetCollector $assetCollector,
        ExtensionRegistry $extensions,
        ContainerInterface $container,
    ): CollectorCoordinator => new CollectorCoordinator(
        $extensions->collectorsWithBuiltIns(
            [
                $requestCollector,
                $logCollector,
                $eventCollector,
                $profilingCollector,
                $dbCollector,
                ...($mailerInstalled ? [$container->get(MailCollector::class)] : []),
                ...($userInstalled ? [$container->get(UserCollector::class)] : []),
                $dumpCollector,
                $assetCollector,
            ],
        ),
    ),
    MailCollector::class => [
        '__construct()' => [
            'emailFactory' => class_exists(EmailFactory::class)
                ? static fn(MessageInterface $message): Email => (new EmailFactory())->create($message)
                : null,
        ],
    ],
    UserCollector::class => [
        '__construct()' => [
            'identityData' => $config['user']['identityData'],
        ],
    ],
];
