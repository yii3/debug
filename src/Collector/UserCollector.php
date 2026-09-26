<?php

declare(strict_types=1);

namespace Yii3\Debug\Collector;

use Closure;
use JsonSerializable;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Helper\{Dump, SensitiveDataRedactor};
use PHPForge\Debug\Panel\User\UserSnapshot;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Throwable;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Rbac\{Item, ManagerInterface};
use Yiisoft\User\CurrentUser;

use function get_object_vars;
use function is_array;
use function method_exists;

/**
 * Captures the current `yiisoft/user` identity and its `yiisoft/rbac` roles and permissions for the User panel.
 *
 * The payload has the shape the Yii2 collector produces, so a capture written by either host renders through the same
 * {@see \PHPForge\Debug\Panel\User\UserPanel}. The identity is read when the response leaves the application, while
 * the session that restores it is still readable; the capture itself runs after the response was sent.
 *
 * Both packages stay optional: {@see CurrentUser} and {@see ManagerInterface} are resolved from the container only when
 * it defines them, so an application without RBAC still captures the identity, and one without `yiisoft/user`
 * captures nothing.
 */
final class UserCollector implements CollectorInterface, RequestObserverInterface
{
    /**
     * Failure raised while the identity was read for the response, reported by the capture instead, or `null`.
     */
    private Throwable|null $failure = null;
    /**
     * Payload read when the response left the application, or `null` before that.
     *
     * @var array<string, mixed>|null
     */
    private array|null $payload = null;
    /**
     * Indicates whether collection is active for the current request lifecycle.
     */
    private bool $started = false;

    /**
     * @param ContainerInterface $container Container resolving the current user and the optional RBAC manager.
     * @param CapturePolicy $capturePolicy Policy naming the identity attributes, at any depth, stored as the redaction
     * placeholder.
     * @param (Closure(IdentityInterface): array<array-key, mixed>)|null $identityData Reader returning the attributes
     * of an identity the default rule cannot see, such as one wrapping an entity; `null` reads `toArray()`, then
     * `jsonSerialize()`, then the public properties.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly CapturePolicy $capturePolicy = new CapturePolicy(),
        private readonly Closure|null $identityData = null,
    ) {}

    /**
     * Encodes the identity into the User panel payload.
     *
     * A capture finalized without a response, such as one written when the application exits early, reads the identity
     * now. A failure kept from the response is thrown here, so the capture records it as a panel failure.
     *
     * @throws Throwable when the current user cannot be resolved, or its identity cannot be restored or read.
     *
     * @return array<string, mixed>|null Encoded User panel payload; `null` when the collector never started or the
     * container defines no current user.
     */
    public function capture(): array|null
    {
        if (!$this->started) {
            return null;
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->payload ?? $this->snapshot();
    }

    /**
     * Ignores the incoming request, since the identity is only final once the application handled it.
     *
     * @param ServerRequestInterface $request Request reaching the debugger middleware.
     */
    public function collectRequest(ServerRequestInterface $request): void {}

    /**
     * Reads the identity before the response is sent and the session is closed.
     *
     * A failure never reaches the application, which already produced its response; it is kept for {@see capture()}.
     *
     * @param ResponseInterface $response Response produced for the captured request.
     */
    public function collectResponse(ResponseInterface $response): void
    {
        if (!$this->started) {
            return;
        }

        try {
            $this->payload = $this->snapshot();
        } catch (Throwable $failure) {
            $this->failure = $failure;
        }
    }

    /**
     * Returns the stable identifier of this collector.
     *
     * @return string Stable ID pairing this collector with its panel.
     */
    public function id(): string
    {
        return 'user';
    }

    /**
     * Stops capturing and discards the identity, or the failure, read for the request.
     */
    public function shutdown(): void
    {
        $this->started = false;
        $this->failure = null;
        $this->payload = null;
    }

    /**
     * Starts capturing, discarding anything left from a previous request.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $this->failure = null;
        $this->payload = null;
        $this->started = true;
    }

    /**
     * Returns the raw attributes of an identity.
     *
     * The configured reader wins; otherwise `toArray()` or `jsonSerialize()` supply them when they return an array, and
     * the public properties are the fallback.
     *
     * @param IdentityInterface $identity Identity of the authenticated user.
     *
     * @return array<array-key, mixed> Attributes keyed by name.
     */
    private function identityData(IdentityInterface $identity): array
    {
        if ($this->identityData !== null) {
            return ($this->identityData)($identity);
        }

        $data = match (true) {
            method_exists($identity, 'toArray') => $identity->toArray(),
            $identity instanceof JsonSerializable => $identity->jsonSerialize(),
            default => null,
        };

        return is_array($data) ? $data : get_object_vars($identity);
    }

    /**
     * Flattens RBAC items into the rows the Yii2 collector records.
     *
     * `yiisoft/rbac` items carry no `data` field, so it is always `''`.
     *
     * @param array<array-key, Item> $items Roles or permissions assigned to the user.
     *
     * @return list<array{name: string, description: string, ruleName: string|null, data: string, createdAt: int|null,
     * updatedAt: int|null}> One row per item, in the order the manager returned them.
     */
    private static function rbacItems(array $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'ruleName' => $item->getRuleName(),
                'data' => '',
                'createdAt' => $item->getCreatedAt(),
                'updatedAt' => $item->getUpdatedAt(),
            ];
        }

        return $rows;
    }

    /**
     * Reads the current identity, its attributes, and its RBAC assignments.
     *
     * An RBAC manager that fails is ignored exactly as the Yii2 collector ignores a misconfigured auth manager, so the
     * identity stays inspectable.
     *
     * @throws Throwable when the current user cannot be resolved or its identity cannot be restored.
     *
     * @return array<string, mixed>|null Encoded payload, the guest payload when nobody is signed in, or `null` when the
     * container defines no current user.
     */
    private function snapshot(): array|null
    {
        if (!$this->container->has(CurrentUser::class)) {
            return null;
        }

        /** @var CurrentUser $user */
        $user = $this->container->get(CurrentUser::class);

        if ($user->isGuest()) {
            return UserSnapshot::capture(
                [
                    'id' => null,
                    'identity' => null,
                    'attributes' => null,
                    'roles' => null,
                    'permissions' => null,
                ],
            )->jsonSerialize();
        }

        $identity = $user->getIdentity();

        $id = $identity->getId();

        $roles = null;
        $permissions = null;

        if ($id !== null && $this->container->has(ManagerInterface::class)) {
            try {
                /** @var ManagerInterface $manager */
                $manager = $this->container->get(ManagerInterface::class);

                $roles = self::rbacItems($manager->getRolesByUserId($id));
                $permissions = self::rbacItems($manager->getPermissionsByUserId($id));
            } catch (Throwable) {
                // Ignore RBAC misconfiguration so the identity panel remains available.
            }
        }

        $identityData = [];

        foreach ($this->capturePolicy->redact($this->identityData($identity)) as $key => $value) {
            $key = (string) $key;

            $identityData[$key] = $this->capturePolicy->isSensitiveKey($key)
                ? SensitiveDataRedactor::PLACEHOLDER
                : Dump::asString($value);
        }

        return UserSnapshot::capture(
            [
                'id' => $id,
                'identity' => $identityData,
                'attributes' => null,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        )->jsonSerialize();
    }
}
