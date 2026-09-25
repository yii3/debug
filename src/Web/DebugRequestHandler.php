<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use RuntimeException;
use Yii3\Debug\Action\{
    CompareAction,
    ConfigAction,
    DbExplainAction,
    DebugActionInterface,
    DownloadMailAction,
    HistoryAction,
    PhpInfoAction,
    ToolbarDataAction,
};
use Yii3\Debug\Exception\Message;
use Yii3\Debug\Middleware\ToolbarOptions;
use Yiisoft\Http\{Method, Status};

use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Serves the debugger endpoints from the toolbar middleware, without a route declaration.
 *
 * Endpoints are resolved lazily from the container, so a request the debugger does not own costs no action
 * instantiation.
 */
final class DebugRequestHandler
{
    /**
     * @var array<string, class-string<DebugActionInterface>> Endpoints keyed by the path segment after the prefix.
     */
    private const array ACTIONS = [
        '' => HistoryAction::class,
        'compare' => CompareAction::class,
        'db-explain' => DbExplainAction::class,
        'download-mail' => DownloadMailAction::class,
        'php-info' => PhpInfoAction::class,
        'toolbar' => ToolbarDataAction::class,
        'view' => ConfigAction::class,
    ];

    /**
     * @param ContainerInterface $container Container the endpoint is resolved from when the path matches.
     * @param ResponseFactoryInterface $responseFactory Factory building the rejection responses.
     * @param ToolbarOptions $options Settings carrying the route prefix the debugger endpoints are served under.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ToolbarOptions $options = new ToolbarOptions(),
    ) {}

    /**
     * Rejects a client that may not reach the debugger.
     *
     * @return ResponseInterface Empty `403 Forbidden` response, marked `Cache-Control: no-store`.
     */
    public function forbidden(): ResponseInterface
    {
        return self::withoutStore($this->responseFactory->createResponse(Status::FORBIDDEN));
    }

    /**
     * Serves the endpoint the path selects.
     *
     * Every response carries `Cache-Control: no-store`, so captured request data never reaches a shared cache or the
     * browser history.
     *
     * @param ServerRequestInterface $request Request targeting the debugger.
     *
     * @throws RuntimeException when the container resolves the endpoint to something else.
     *
     * @return ResponseInterface Endpoint response, an empty `404 Not Found` for an unknown path, or an empty
     * `405 Method Not Allowed` for a write method.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if ($method !== Method::GET && $method !== Method::HEAD) {
            return self::withoutStore(
                $this->responseFactory
                    ->createResponse(Status::METHOD_NOT_ALLOWED)
                    ->withHeader('Allow', Method::GET . ', ' . Method::HEAD),
            );
        }

        $action = $this->action($request);

        if ($action === null) {
            return self::withoutStore($this->responseFactory->createResponse(Status::NOT_FOUND));
        }

        $endpoint = $this->container->get($action);

        if (!$endpoint instanceof DebugActionInterface) {
            throw new RuntimeException(
                Message::DEBUG_SERVICE_UNAVAILABLE->getMessage($action),
            );
        }

        return self::withoutStore($endpoint($request));
    }

    /**
     * Resolves the endpoint the request path selects.
     *
     * @param ServerRequestInterface $request Request targeting the debugger.
     *
     * @return class-string<DebugActionInterface>|null Endpoint serving the path, or `null` when the debugger owns no
     * endpoint for it.
     */
    private function action(ServerRequestInterface $request): string|null
    {
        $path = $request->getUri()->getPath();

        if ($path === $this->options->routePrefix) {
            return self::ACTIONS[''];
        }

        $prefix = $this->options->routePrefix . '/';

        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        return self::ACTIONS[substr($path, strlen($prefix))] ?? null;
    }

    /**
     * Marks a response as never cacheable, keeping the headers and body it already carries.
     *
     * @param ResponseInterface $response Response the debugger returns.
     *
     * @return ResponseInterface Response carrying `Cache-Control: no-store`.
     */
    private static function withoutStore(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Cache-Control', 'no-store');
    }
}
