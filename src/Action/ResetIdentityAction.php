<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\User\UserSwitch;
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};

/**
 * Restores the main (pre-switch) identity for an allowed debug client.
 *
 * Follows the same deny-by-default gate as {@see SetIdentityAction}: switching must be enabled and a main user
 * must be authenticated.
 */
final readonly class ResetIdentityAction
{
    /**
     * @param LocalAccessChecker $accessChecker Debug interface access policy.
     * @param ResponseBuilder $responseBuilder JSON response factory.
     * @param UserSwitch $userSwitch Identity switch service.
     * @param bool $switchEnabled Whether user switching is enabled (deny by default).
     */
    public function __construct(
        private LocalAccessChecker $accessChecker,
        private ResponseBuilder $responseBuilder,
        private UserSwitch $userSwitch,
        private bool $switchEnabled = false,
    ) {}

    /**
     * Restores the main user identity.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return ResponseInterface JSON result: the restored main ID, or a forbidden response.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->json(['error' => 'Forbidden'], 403);
        }

        if (!$this->switchEnabled || $this->userSwitch->getMainUserId() === null) {
            return $this->responseBuilder->json(['error' => 'User switching is not allowed.'], 403);
        }

        $this->userSwitch->reset();

        return $this->responseBuilder->json(['id' => $this->userSwitch->getMainUserId()]);
    }
}
