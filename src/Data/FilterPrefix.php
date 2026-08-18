<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

/**
 * Freezes the `Prefix[attribute]` query-parameter vocabulary shared by every debug-panel filter form.
 *
 * Both adapters emit filter inputs named `<prefix>[<attribute>]` using these constants, so deep links and the shared
 * JavaScript filter bridge behave identically across frameworks.
 */
final class FilterPrefix
{
    public const string ASSET = 'Asset';

    public const string DB = 'Db';

    public const string DEBUG = 'Debug';

    public const string EVENT = 'Event';

    public const string LOG = 'Log';

    public const string MAIL = 'Mail';

    public const string PROFILE = 'Profile';

    public const string QUEUE = 'Queue';

    public const string ROUTER = 'Router';

    public const string TIMELINE = 'Timeline';

    public const string USER = 'User';
}
