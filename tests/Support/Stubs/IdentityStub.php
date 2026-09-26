<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Support\Stubs;

use Yiisoft\Auth\IdentityInterface;

final class IdentityStub implements IdentityInterface
{
    public string $email = 'admin@example.com';
    public string $password_hash = '$2y$13$secret';
    public string $username = 'admin';
    private string $authKey = 'hidden';

    public function __construct(public string|null $id = '100') {}

    public function authKey(): string
    {
        return $this->authKey;
    }

    public function getId(): string|null
    {
        return $this->id;
    }
}
