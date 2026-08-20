<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Web;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\CsrfRequestValidator;
use Yiisoft\Csrf\StubCsrfToken;

/**
 * Unit tests for {@see CsrfRequestValidator} covering unavailable, body, header, and invalid token paths.
 */
final class CsrfRequestValidatorTest extends TestCase
{
    public function testRejectsMissingInvalidAndNonStringBodyTokens(): void
    {
        $validator = new CsrfRequestValidator(new StubCsrfToken('valid'));

        self::assertFalse($validator->validates(new ServerRequest('POST', '/')), 'A protected request needs a token.');
        self::assertFalse(
            $validator->validates((new ServerRequest('POST', '/'))->withParsedBody(['_csrf' => 'invalid'])),
            'A mismatched body token must be rejected.',
        );
        self::assertFalse(
            $validator->validates((new ServerRequest('POST', '/'))->withParsedBody(['_csrf' => ['invalid']])),
            'A non-string body token must be rejected.',
        );
    }
    public function testRejectsRequestsWhenCsrfProtectionIsUnavailable(): void
    {
        self::assertFalse(
            (new CsrfRequestValidator())->validates(new ServerRequest('POST', '/')),
            'User switching must fail closed without a CSRF token service.',
        );
    }
    public function testValidatesBodyAndHeaderTokens(): void
    {
        $validator = new CsrfRequestValidator(new StubCsrfToken('valid'));

        self::assertTrue(
            $validator->validates((new ServerRequest('POST', '/'))->withParsedBody(['_csrf' => 'valid'])),
            'The standard form field must be accepted.',
        );
        self::assertTrue(
            $validator->validates((new ServerRequest('POST', '/'))->withHeader('X-CSRF-Token', 'valid')),
            'The standard request header must be accepted.',
        );
    }
}
