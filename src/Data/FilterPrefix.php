<?php

declare(strict_types=1);

namespace Yii3\Debug\Data;

use PHPForge\Debug\Data\FilterPrefix as CoreFilterPrefix;

/**
 * Backward-compatible Yii3 facade for the shared filter-prefix vocabulary.
 *
 * New integrations should use {@see CoreFilterPrefix} directly.
 */
final class FilterPrefix
{
    public const string ASSET = CoreFilterPrefix::ASSET;

    public const string DB = CoreFilterPrefix::DB;

    public const string DEBUG = CoreFilterPrefix::DEBUG;

    public const string EVENT = CoreFilterPrefix::EVENT;

    public const string LOG = CoreFilterPrefix::LOG;

    public const string MAIL = CoreFilterPrefix::MAIL;

    public const string PROFILE = CoreFilterPrefix::PROFILE;

    public const string QUEUE = CoreFilterPrefix::QUEUE;

    public const string ROUTER = CoreFilterPrefix::ROUTER;

    public const string TIMELINE = CoreFilterPrefix::TIMELINE;

    public const string USER = CoreFilterPrefix::USER;
}
