# Application integration examples

These examples extend an existing Yii 3 application. They do not create routes, database credentials, an event
dispatcher, asset services, or a writable runtime directory. Install the debugger and rebuild the merge plan as
described in [Getting started](index.md).

## Compose the runner groups

Keep the application's base groups and add Web groups to every intended HTTP aggregate in
`config/configuration.php`:

```php
<?php

declare(strict_types=1);

return [
    'config-plugin' => [
        'params' => 'common/params.php',
        'params-site' => ['$params', '$params-web', 'site/params.php'],
        'params-api' => ['$params', '$params-web', 'api/params.php'],
        'di' => 'common/di/*.php',
        'di-site' => ['$di', '$di-web', 'site/di/*.php'],
        'di-api' => ['$di', '$di-web', 'api/di/*.php'],
        'di-providers' => 'common/di-providers.php',
        'di-providers-site' => '$di-providers',
        'di-providers-api' => '$di-providers',
        'events' => [],
        'events-site' => ['$events', '$events-web'],
        'events-api' => ['$events', '$events-web'],
        'bootstrap' => 'common/bootstrap.php',
        'bootstrap-site' => '$bootstrap',
        'bootstrap-api' => '$bootstrap',
    ],
    'config-plugin-options' => [
        'source-directory' => 'config',
    ],
];
```

If the application already defines these keys, extend them instead of replacing unrelated group members. Run
`composer yii-config-rebuild` after changing this file.

## Configure application metadata and log targets

Add application-owned values in `config/common/params.php`. Named target keys allow the Yii Config Plugin merge to
retain the debugger target while the application adds its own:

```php
<?php

declare(strict_types=1);

use Yiisoft\Log\Target\File\FileTarget;

return [
    'yii3/debug' => [
        'application' => [
            'name' => 'Example application',
            'version' => '1.0',
        ],
    ],
    'yiisoft/log' => [
        'targets' => [
            'file' => FileTarget::class,
        ],
    ],
];
```

The package supplies the `debug` and `stream` targets. Reusing either key replaces that entry deliberately.

Build the logger from the final parameter array in `config/common/di/logger.php`:

```php
<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\Log\{Logger, StreamTarget};

/** @var array $params */

return [
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from(
                array_values($params['yiisoft/log']['targets'] ?? [StreamTarget::class]),
            ),
        ],
    ],
];
```

## Extend each middleware pipeline

Prepend the package list in every application definition that should capture requests. This example shows only the
relevant part of a Site definition:

```php
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Router\Middleware\Router;

'withMiddlewares()' => [[
    ...($params['yiisoft/middleware-dispatcher']['middlewares'] ?? []),
    ErrorCatcher::class,
    Router::class,
]],
```

Repeat the spread in an API pipeline only when that pipeline should capture requests and serve Debug endpoints. JSON
responses are captured but do not receive an HTML toolbar.

## Select the aggregates in the runner

The runner names must match the aggregates above:

```php
new HttpApplicationRunner(
    rootPath: $root,
    environment: $environment,
    bootstrapGroup: 'bootstrap-site',
    eventsGroup: 'events-site',
    diGroup: 'di-site',
    diProvidersGroup: 'di-providers-site',
    paramsGroup: 'params-site',
    nestedParamsGroups: ['params', 'params-web'],
    nestedEventsGroups: ['events', 'events-web'],
)->run();
```

Set `APP_ENV=dev` independently; the `environment` argument selects Yii Config Plugin overlays and does not enable the
debugger by itself.

## Next steps

- 📚 [Getting started](index.md)
- ⚙️ [Configuration reference](configuration.md)
- 🤖 [AI assistance](agents.md)
- 🧪 [Testing guide](testing.md)

[Back to documentation](../README.md#documentation)
