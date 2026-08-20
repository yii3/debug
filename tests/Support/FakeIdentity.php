<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support;

use Yiisoft\Auth\IdentityInterface;

/**
 * Provides a fixed-ID identity fixture for the user-switch tests.
 */
final readonly class FakeIdentity implements IdentityInterface
{
    public function __construct(
        public string $id,
        public string $username = '',
        public string $email = '',
        public string $status = '',
        public string $created_at = '',
        public string $updated_at = '',
        public string $access_token = '',
    ) {}

    public function getId(): string
    {
        return $this->id;
    }
}
