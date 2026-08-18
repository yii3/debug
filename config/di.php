<?php

declare(strict_types=1);

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\StreamFactoryInterface;
use Yii3\Debug\Action\{ResetIdentityAction, SetIdentityAction};
use Yii3\Debug\Collector\{
    AssetCollector,
    ConfigCollector,
    DbCollector,
    EventCollector,
    LogCollector,
    ProfilingCollector,
    RequestCollector,
    RouterCollector,
    UserCollector,
};
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Panel\{
    AssetPanel,
    ConfigPanel,
    DbPanel,
    EventPanel,
    LogPanel,
    ProfilingPanel,
    RequestPanel,
    RouterPanel,
    UserPanel,
};
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\User\{IdentityListProviderInterface, UserSwitch};
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Db\Profiler\ProfilerInterface;
use Yiisoft\Definitions\{Reference, ReferencesArray};
use Yiisoft\User\CurrentUser;
use Yiisoft\View\WebView;

/**
 * @var array<string, mixed> $params
 */
$config = $params['yii3/debug'];

$hasDb = interface_exists(ProfilerInterface::class);
$hasUser = class_exists(CurrentUser::class);

$corePanels = [
    Reference::to(ConfigPanel::class),
    Reference::to(RequestPanel::class),
    Reference::to(RouterPanel::class),
    ...($hasUser ? [Reference::to(UserPanel::class)] : []),
    Reference::to(LogPanel::class),
    ...($hasDb ? [Reference::to(DbPanel::class)] : []),
    Reference::to(ProfilingPanel::class),
    Reference::to(EventPanel::class),
    Reference::to(AssetPanel::class),
];

$dbDefinitions = $hasDb
    ? [ProfilerInterface::class => Reference::to(DbCollector::class)]
    : [];

$userDefinitions = $hasUser
    ? [
        UserPanel::class => [
            '__construct()' => [
                'userSwitch' => Reference::to(UserSwitch::class),
                'identities' => Reference::optional(IdentityListProviderInterface::class),
                'csrfToken' => Reference::optional(CsrfTokenInterface::class),
                'switchEnabled' => $config['userSwitch']['enabled'],
                'routePrefix' => $config['routePrefix'],
            ],
        ],
        SetIdentityAction::class => [
            '__construct()' => [
                'switchEnabled' => $config['userSwitch']['enabled'],
            ],
        ],
        ResetIdentityAction::class => [
            '__construct()' => [
                'switchEnabled' => $config['userSwitch']['enabled'],
            ],
        ],
    ]
    : [];

return [
    ...$dbDefinitions,
    ...$userDefinitions,
    SnapshotStore::class => static fn(): SnapshotStore => new SnapshotStore(
        path: $config['path'],
        dirMode: $config['dirMode'],
        fileMode: $config['fileMode'],
    ),
    CollectorCoordinator::class => [
        '__construct()' => [
            'collectors' => [
                Reference::to(ConfigCollector::class),
                Reference::to(RequestCollector::class),
                Reference::to(RouterCollector::class),
                ...($hasUser ? [Reference::to(UserCollector::class)] : []),
                Reference::to(LogCollector::class),
                ...($hasDb ? [Reference::to(DbCollector::class)] : []),
                Reference::to(ProfilingCollector::class),
                Reference::to(EventCollector::class),
                Reference::to(AssetCollector::class),
                ...ReferencesArray::from($config['collectors']),
            ],
        ],
    ],
    ConfigCollector::class => static fn(): ConfigCollector => new ConfigCollector($config['application']),
    ConfigPanel::class => static fn(): ConfigPanel => new ConfigPanel($config['routePrefix']),
    LocalAccessChecker::class => static fn(): LocalAccessChecker => new LocalAccessChecker($config['allowedIPs']),
    ToolbarDataFactory::class => [
        '__construct()' => [
            'panels' => [
                ...$corePanels,
                ...ReferencesArray::from($config['panels']),
            ],
            'routePrefix' => $config['routePrefix'],
            'position' => $config['toolbar']['position'],
            'height' => $config['toolbar']['height'],
        ],
    ],
    ToolbarRenderer::class => static fn(
        WebView $view,
        AssetManager $assetManager,
        Aliases $aliases,
    ): ToolbarRenderer => new ToolbarRenderer(
        $view,
        $assetManager,
        $aliases->get($config['viewPath']),
    ),
    DebugPageRenderer::class => [
        '__construct()' => [
            'viewPath' => $config['viewPath'],
            'routePrefix' => $config['routePrefix'],
            'panels' => [
                ...$corePanels,
                ...ReferencesArray::from($config['panels']),
            ],
        ],
    ],
    ToolbarMiddleware::class => static fn(
        CollectorCoordinator $coordinator,
        RequestCollector $requestCollector,
        SnapshotStore $store,
        ToolbarRenderer $renderer,
        StreamFactoryInterface $streamFactory,
        LocalAccessChecker $accessChecker,
    ): ToolbarMiddleware => new ToolbarMiddleware(
        coordinator: $coordinator,
        requestCollector: $requestCollector,
        store: $store,
        renderer: $renderer,
        streamFactory: $streamFactory,
        accessChecker: $accessChecker,
        historySize: $config['historySize'],
        routePrefix: $config['routePrefix'],
        skipUrls: $config['toolbar']['skipUrls'],
        position: $config['toolbar']['position'],
        height: $config['toolbar']['height'],
    ),
];
