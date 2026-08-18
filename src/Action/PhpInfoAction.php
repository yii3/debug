<?php

declare(strict_types=1);

namespace Yii3\Debug\Action;

use PHPForge\Debug\Theme\ThemeResolver;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Yii3\Debug\Web\{DebugPageRenderer, LocalAccessChecker, ResponseBuilder};

/**
 * Serves the standalone phpinfo report for the Yii3 debugger.
 */
final readonly class PhpInfoAction
{
    public function __construct(
        private LocalAccessChecker $accessChecker,
        private DebugPageRenderer $renderer,
        private ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Returns the phpinfo report for an allowed client.
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return ResponseInterface Report page or forbidden response.
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->accessChecker->allows($request)) {
            return $this->responseBuilder->text('Forbidden', 403);
        }

        return $this->responseBuilder->html(
            $this->renderer->phpInfo(
                ThemeResolver::resolve($request->getCookieParams(), $request->getQueryParams()),
            ),
        );
    }
}
