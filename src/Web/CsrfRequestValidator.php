<?php

declare(strict_types=1);

namespace Yii3\Debug\Web;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Csrf\CsrfTokenInterface;

use function is_array;
use function is_string;

/**
 * Validates CSRF protection for debugger POST actions and rejects requests when no token service is available.
 */
final readonly class CsrfRequestValidator
{
    public function __construct(private CsrfTokenInterface|null $token = null) {}

    /**
     * Returns whether the request carries a valid token.
     */
    public function validates(ServerRequestInterface $request): bool
    {
        if ($this->token === null) {
            return false;
        }

        $body = $request->getParsedBody();

        $value = is_array($body) ? ($body['_csrf'] ?? null) : null;

        if (!is_string($value) || $value === '') {
            $value = $request->getHeaderLine('X-CSRF-Token');
        }

        return $value !== '' && $this->token->validate($value);
    }
}
