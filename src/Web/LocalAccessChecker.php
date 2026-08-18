<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function is_string;

/**
 * Restricts the Yii3 debug interface to explicitly allowed client IP addresses.
 */
final readonly class LocalAccessChecker
{
    /**
     * @param list<string> $allowedIPs Client IP addresses allowed to use the debug interface.
     */
    public function __construct(private array $allowedIPs = ['127.0.0.1', '::1']) {}

    /**
     * Returns whether a server request may use the debug interface.
     *
     * Usage example:
     *
     * ```php
     * $allowed = $checker->allows($request);
     * ```
     *
     * @param ServerRequestInterface $request Incoming server request.
     *
     * @return bool Whether the client IP is allowed.
     */
    public function allows(ServerRequestInterface $request): bool
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($remoteAddress) && in_array($remoteAddress, $this->allowedIPs, true);
    }
}
