<?php

declare(strict_types=1);

use Yii3\Debug\Action\{ResetIdentityAction, SetIdentityAction};
use Yii3\Debug\Panel\{
    AssetPanel,
    ConfigPanel,
    DbPanel,
    EventPanel,
    LogPanel,
    PanelGrid,
    ProfilingPanel,
    RequestPanel,
    RouterPanel,
    TimelinePanel,
    UserPanel,
};
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\User\{IdentityListProviderInterface, UserSwitch};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Csrf\CsrfTokenInterface;
use Yiisoft\Db\Profiler\ProfilerInterface;
use Yiisoft\Definitions\{Reference, ReferencesArray};
use Yiisoft\User\CurrentUser;

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
    Reference::to(TimelinePanel::class),
    Reference::to(EventPanel::class),
    Reference::to(AssetPanel::class),
];

return [
    ProfilingPanel::class => [
        '__construct()' => [
            'grid' => Reference::to(PanelGrid::class),
        ],
    ],
    ...($hasUser ? [
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
    ] : []),
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
];
