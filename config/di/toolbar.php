<?php

declare(strict_types=1);

use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Middleware\{DebugRouteMiddleware, RequestCaptureMiddleware, ToolbarOptions};
use Yii3\Debug\Panel\BuiltInPanelList;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\NetworkUtilities\IpRanges;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

$allowedIpRanges = new IpRanges($config['allowedIPs']);

return [
    ToolbarOptions::class => static fn(): ToolbarOptions => ToolbarOptions::fromParams($config),
    DebugRouteMiddleware::class => [
        '__construct()' => [
            'allowedIpRanges' => $allowedIpRanges,
        ],
    ],
    RequestCaptureMiddleware::class => [
        '__construct()' => [
            'allowedIpRanges' => $allowedIpRanges,
        ],
    ],
    ToolbarDataFactory::class => [
        'withExtensionPanels()' => [
            DynamicReference::to(
                static fn(
                    ExtensionRegistry $extensions,
                    BuiltInPanelList $builtInPanels,
                ): array => $extensions->panelsWithBuiltIns($builtInPanels->panels()),
            ),
        ],
    ],
    ToolbarRenderer::class => [
        '__construct()' => [
            'viewPath' => DynamicReference::to(
                static fn(Aliases $aliases): string => $aliases->get($config['viewPath']),
            ),
        ],
    ],
];
