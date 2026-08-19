<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\User\UserSwitch;
use Yii3\Debug\Web\{CsrfRequestValidator, LocalAccessChecker, ResponseBuilder};
use Yiisoft\Auth\IdentityRepositoryInterface;

use function is_array;
use function is_int;
use function is_string;

/**
 * Switches the authenticated identity to the requested user for an allowed debug client.
 *
 * The switch is denied by default: it requires the adapter configuration to enable user switching AND an
 * authenticated main user — an unauthenticated request can never impersonate, regardless of client IP.
 */
final readonly class SetIdentityAction
{
    /**
     * @param LocalAccessChecker $accessChecker Debug interface access policy.
     * @param ResponseBuilder $responseBuilder JSON response factory.
     * @param UserSwitch $userSwitch Identity switch service.
     * @param IdentityRepositoryInterface $identityRepository Repository resolving identities by ID.
     * @param bool $switchEnabled Whether user switching is enabled (deny by default).
     * @param CsrfRequestValidator|null $csrfValidator Optional CSRF validator for state-changing requests.
     */
    public function __construct(
        private LocalAccessChecker $accessChecker,
        private ResponseBuilder $responseBuilder,
        private UserSwitch $userSwitch,
        private IdentityRepositoryInterface $identityRepository,
        private bool $switchEnabled = false,
        private CsrfRequestValidator|null $csrfValidator = null,
    ) {}

    /**
     * Switches to the identity named by the `user_id` body parameter.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return ResponseInterface JSON result: the impersonated ID, a validation error, or a forbidden response.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->json(['error' => 'Forbidden'], 403);
        }

        if (!$this->switchEnabled || $this->userSwitch->getMainUserId() === null) {
            return $this->responseBuilder->json(['error' => 'User switching is not allowed.'], 403);
        }

        if ($this->csrfValidator !== null && !$this->csrfValidator->validates($request)) {
            return $this->responseBuilder->json(['error' => 'Invalid CSRF token.'], 422);
        }

        $body = $request->getParsedBody();
        $userId = is_array($body) ? ($body['user_id'] ?? null) : null;

        if (!is_string($userId) && !is_int($userId)) {
            return $this->responseBuilder->json(['error' => 'A `user_id` parameter is required.'], 400);
        }

        $identity = $this->identityRepository->findIdentity((string) $userId);

        if ($identity === null) {
            return $this->responseBuilder->json(['error' => 'User not found.'], 404);
        }

        $this->userSwitch->setUser($identity);

        return $this->responseBuilder->json(['id' => $identity->getId()]);
    }
}
