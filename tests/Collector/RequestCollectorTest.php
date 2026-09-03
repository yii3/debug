<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Collector;

use LogicException;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Yii3\Debug\Collector\RequestCollector;
use Yii3\Debug\Tests\Support\HelperFactory;

use function strlen;

use const SEEK_SET;
use const UPLOAD_ERR_OK;

/**
 * Unit tests for PSR-7 request and response capture in the shared Request panel shape.
 */
#[Group('collector')]
#[Group('request')]
final class RequestCollectorTest extends TestCase
{
    public function testCapturePreservesCanonicalRequestMetadata(): void
    {
        $collector = new RequestCollector();

        $request = HelperFactory::createRequest(
            method: 'POST',
            uri: 'https://example.test/orders/42?status=open',
            headers: [
                'Content-Type' => 'application/json',
                'User-Agent' => 'client shockwave flash',
                'X-Multi' => ['first', 'second'],
                'X-Pjax' => 'true',
                'X-Requested-With' => 'xMlHtTpReQuEsT',
            ],
            parsedBody: ['order' => 42],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        )
        ->withBody(HelperFactory::createStream('{"order":42}'))
        ->withUploadedFiles(
            [
                'avatar' => HelperFactory::createUploadedFile(
                    name: 'avatar.png',
                    type: 'image/png',
                    content: 'image',
                ),
                'documents' => [
                    'invoice' => HelperFactory::createUploadedFile(
                        name: 'invoice.pdf',
                        type: 'application/pdf',
                        content: 'invoice',
                    ),
                ],
            ],
        );

        self::assertSame(
            'request',
            $collector->id(),
            'Collector ID must match the Request panel payload key.',
        );
        self::assertNull(
            $collector->capture(),
            'An inactive collector must not produce a snapshot.',
        );

        $collector->startup();
        $collector->collectRequest($request);

        self::assertNull(
            $collector->capture(),
            'A request without its response must not build an invalid status-less snapshot.',
        );

        $collector->collectResponse(
            HelperFactory::createResponse(
                201,
                [
                    'X-Result' => 'created',
                    'X-Multi' => [
                        'third',
                        'fourth',
                    ],
                ],
            ),
        );

        $snapshot = $collector->capture();

        self::assertInstanceOf(
            RequestSnapshot::class,
            $snapshot,
            'A complete lifecycle must produce the canonical Request snapshot.',
        );
        self::assertSame(
            201,
            $snapshot->statusCode,
            'Snapshot status must match the response status.',
        );

        $data = $snapshot->data();

        self::assertSame(
            '',
            $data['route'] ?? null,
            'An unavailable route must retain an empty identifier.',
        );
        self::assertArrayHasKey(
            'action',
            $data,
            'An unavailable action must retain its canonical slot.',
        );
        self::assertNull(
            $data['action'],
            'An unavailable action must retain a null value.',
        );
        self::assertSame(
            [],
            $data['actionParams'] ?? null,
            'Unavailable route arguments must remain empty.',
        );
        self::assertSame(
            [
                'isAjax' => true,
                'isFlash' => true,
                'isPjax' => true,
                'isSecureConnection' => true,
                'method' => 'POST',
            ],
            $data['general'] ?? null,
            'General request flags must match Yii request semantics.',
        );
        self::assertSame(
            [
                'Content Type' => 'application/json',
                'Decoded' => ['order' => 42],
                'Raw' => '{"order":42}',
            ],
            $data['requestBody'] ?? null,
            'Raw and parsed request bodies must remain inspectable.',
        );
        self::assertSame(
            ['status' => 'open'],
            $data['GET'] ?? null,
            'Query parameters must fill GET.',
        );
        self::assertSame(
            ['order' => 42],
            $data['POST'] ?? null,
            'Parsed body parameters must fill POST.',
        );
        self::assertSame(
            [
                'avatar' => [
                    'name' => 'avatar.png',
                    'type' => 'image/png',
                    'size' => 5,
                    'error' => UPLOAD_ERR_OK,
                ],
                'documents' => [
                    'invoice' => [
                        'name' => 'invoice.pdf',
                        'type' => 'application/pdf',
                        'size' => 7,
                        'error' => UPLOAD_ERR_OK,
                    ],
                ],
            ],
            $data['FILES'] ?? null,
            'Nested uploaded files must retain useful client metadata without exposing temporary paths.',
        );
        self::assertSame(
            ['REMOTE_ADDR' => '127.0.0.1'],
            $data['SERVER'] ?? null,
            'Server parameters must fill SERVER.',
        );
        self::assertSame(
            [],
            $data['SESSION'] ?? null,
            'Unavailable session data must retain an empty bucket.',
        );

        $requestHeaders = $data['requestHeaders'] ?? null;

        self::assertIsArray(
            $requestHeaders,
            'Request headers must remain an array.',
        );
        self::assertSame(
            'application/json',
            $requestHeaders['Content-Type'] ?? null,
            'Single-value request headers must collapse to strings.',
        );
        self::assertSame(
            ['first', 'second'],
            $requestHeaders['X-Multi'] ?? null,
            'Multi-value request headers must remain lists.',
        );

        $responseHeaders = $data['responseHeaders'] ?? null;

        self::assertIsArray(
            $responseHeaders,
            'Response headers must remain an array.',
        );
        self::assertSame(
            'created',
            $responseHeaders['X-Result'] ?? null,
            'Single-value response headers must collapse to strings.',
        );
        self::assertSame(
            ['third', 'fourth'],
            $responseHeaders['X-Multi'] ?? null,
            'Multi-value response headers must remain lists.',
        );
    }

    public function testCaptureRedactsDefaultSecretsAcrossRequestAndResponse(): void
    {
        $collector = new RequestCollector();

        $request = HelperFactory::createRequest(
            method: 'POST',
            uri: 'https://example.test/login?token=query-secret',
            headers: ['Authorization' => 'Bearer header-secret', 'Content-Type' => 'application/json'],
            parsedBody: ['password' => 'body-secret'],
            serverParams: [
                'DATABASE_HOST' => 'database.internal',
                'DB_PASSWORD' => 'database-secret',
                'HTTP_REFERER' => 'https://example.test/form?token=server-referer-secret&step=2',
                'QUERY_STRING' => 'token=query-string-secret&filter=open',
                'REQUEST_URI' => '/login?password=request-uri-secret&view=full',
                'tokenizer' => 'visible',
            ],
        )
        ->withBody(HelperFactory::createStream('{"password":"body-secret"}'))
        ->withCookieParams(['session_id' => 'cookie-secret'])
        ->withHeader('Referer', 'https://example.test/login?token=referer-secret&view=full')
        ->withHeader('X-Callback-URL', 'https://example.test/callback?access_token=callback-secret&mode=fast')
        ->withHeader('Link', '<https://example.test/next?token=link-secret>; rel="next"');

        $collector->startup();
        $collector->collectRequest($request);
        $collector->collectResponse(
            HelperFactory::createResponse(
                302,
                [
                    'Location' => 'https://example.test/next?token=location-secret&view=full',
                    'Set-Cookie' => 'session_id=response-secret',
                    'X-Inertia-Location' => 'https://example.test/inertia?token=inertia-secret&view=full',
                ],
            ),
        );

        $snapshot = $collector->capture();

        self::assertNotNull(
            $snapshot,
            'A redacted request must still produce a snapshot.',
        );

        $data = $snapshot->data();

        $requestHeaders = $data['requestHeaders'] ?? null;
        $requestBody = $data['requestBody'] ?? null;
        $query = $data['GET'] ?? null;
        $server = $data['SERVER'] ?? null;
        $responseHeaders = $data['responseHeaders'] ?? null;

        self::assertIsArray(
            $requestHeaders,
            'Request headers must remain an array after redaction.',
        );
        self::assertIsArray(
            $requestBody,
            'Request body must remain an array after redaction.',
        );
        self::assertIsArray(
            $query,
            'Query parameters must remain an array after redaction.',
        );
        self::assertIsArray(
            $server,
            'Server parameters must remain an array after redaction.',
        );
        self::assertIsArray(
            $responseHeaders,
            'Response headers must remain an array after redaction.',
        );

        $decodedBody = $requestBody['Decoded'] ?? null;

        self::assertIsArray(
            $decodedBody,
            'Decoded request body must remain an array after redaction.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $requestHeaders['Authorization'] ?? null,
            'Authorization headers must be redacted by default.',
        );
        self::assertSame(
            'https://example.test/login?token=%5Bredacted%5D&view=full',
            $requestHeaders['Referer'] ?? null,
            'Referer query secrets must be redacted without hiding safe URL context.',
        );
        self::assertSame(
            'https://example.test/callback?access_token=%5Bredacted%5D&mode=fast',
            $requestHeaders['X-Callback-URL'] ?? null,
            'Custom URL header query secrets must be redacted without hiding safe URL context.',
        );
        self::assertSame(
            '<https://example.test/next?token=%5Bredacted%5D>; rel="next"',
            $requestHeaders['Link'] ?? null,
            'Compound URL headers must redact secret assignments without corrupting their field structure.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $decodedBody['password'] ?? null,
            'Decoded body secrets must be redacted by default.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $requestBody['Raw'] ?? null,
            'Raw bodies must be suppressed when decoded values require redaction.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $query['token'] ?? null,
            'Query tokens must be redacted by default.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $data['COOKIE'] ?? null,
            'Cookie buckets must be redacted by default.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $server['DB_PASSWORD'] ?? null,
            'Environment-style credential keys must be redacted by default.',
        );
        self::assertSame(
            'https://example.test/form?token=%5Bredacted%5D&step=2',
            $server['HTTP_REFERER'] ?? null,
            'Server referer URLs must redact sensitive query values.',
        );
        self::assertSame(
            'token=%5Bredacted%5D&filter=open',
            $server['QUERY_STRING'] ?? null,
            'Server query strings must redact sensitive values while preserving safe parameters.',
        );
        self::assertSame(
            '/login?password=%5Bredacted%5D&view=full',
            $server['REQUEST_URI'] ?? null,
            'Server request URIs must redact sensitive query values while preserving the path.',
        );
        self::assertSame(
            'database.internal',
            $server['DATABASE_HOST'] ?? null,
            'Safe server values must remain visible.',
        );
        self::assertSame(
            'visible',
            $server['tokenizer'] ?? null,
            'Safe words merely containing a credential term must remain visible.',
        );
        self::assertSame(
            SensitiveDataRedactor::PLACEHOLDER,
            $responseHeaders['Set-Cookie'] ?? null,
            'Response cookies must be redacted by default.',
        );
        self::assertSame(
            'https://example.test/next?token=%5Bredacted%5D&view=full',
            $responseHeaders['Location'] ?? null,
            'Response location query secrets must be redacted without hiding safe URL context.',
        );
        self::assertSame(
            'https://example.test/inertia?token=%5Bredacted%5D&view=full',
            $responseHeaders['X-Inertia-Location'] ?? null,
            'Extension location query secrets must be redacted without hiding safe URL context.',
        );
    }

    public function testCaptureUsesTheInjectedBodyLimit(): void
    {
        $collector = new RequestCollector(capturePolicy: new CapturePolicy(maxBodyBytes: 4));

        $source = HelperFactory::createStream('abcdefgh');

        $bytesRead = 0;

        $body = self::createStub(StreamInterface::class);

        $body
            ->method('__toString')
            ->willThrowException(
                new LogicException(
                    'The request collector must not cast a body stream to a string.',
                ),
            );
        $body
            ->method('isSeekable')
            ->willReturn(true);
        $body
            ->method('tell')
            ->willReturnCallback(
                static fn(): int => $source->tell(),
            );
        $body
            ->method('eof')
            ->willReturnCallback(
                static fn(): bool => $source->eof(),
            );
        $body
            ->method('seek')
            ->willReturnCallback(
                static function (int $offset, int $whence = SEEK_SET) use ($source): void {
                    $source->seek($offset, $whence);
                },
            );
        $body
            ->method('read')
            ->willReturnCallback(
                static function (int $length) use (&$bytesRead, $source): string {
                    $chunk = $source->read($length);
                    $bytesRead += strlen($chunk);

                    return $chunk;
                },
            );
        $body->seek(2);

        $request = HelperFactory::createRequest(
            method: 'POST',
            uri: 'https://example.test/body',
            headers: ['Content-Type' => 'text/plain'],
        )->withBody($body);

        $collector->startup();
        $collector->collectRequest($request);
        $collector->collectResponse(HelperFactory::createResponse());
        $data = $collector->capture()?->data() ?? [];

        $requestBody = $data['requestBody'] ?? null;

        self::assertIsArray(
            $requestBody,
            'A retained request body must remain an array.',
        );
        self::assertSame(
            'abcd' . SensitiveDataRedactor::TRUNCATED,
            $requestBody['Raw'] ?? null,
            'Raw request bodies must honor the injected persistent-capture limit.',
        );
        self::assertSame(
            5,
            $bytesRead,
            'Body capture must read at most the configured limit plus one truncation-detection byte.',
        );
        self::assertSame(
            2,
            $body->tell(),
            'Bounded body capture must restore the original stream cursor.',
        );
    }

    public function testCollectRequestDoesNotConsumeBodyStreams(): void
    {
        $collector = new RequestCollector();

        $seekable = HelperFactory::createStream('seekable-body');

        $seekable->seek(4);
        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'POST',
                'https://example.test/',
            )->withBody($seekable),
        );

        self::assertSame(
            4,
            $seekable->tell(),
            'Reading a seekable body must restore its original cursor.',
        );

        $collector->collectResponse(HelperFactory::createResponse());

        $data = $collector->capture()?->data() ?? [];

        $requestBody = $data['requestBody'] ?? null;

        self::assertIsArray(
            $requestBody,
            'A seekable raw body must be retained.',
        );
        self::assertSame(
            'seekable-body',
            $requestBody['Raw'] ?? null,
            'The complete seekable body must be captured regardless of its original cursor.',
        );

        $collector->shutdown();

        $inner = HelperFactory::createStream('non-seekable-body');

        $nonSeekable = self::createStub(StreamInterface::class);

        $nonSeekable
            ->method('isSeekable')
            ->willReturn(false);
        $nonSeekable
            ->method('getContents')
            ->willReturnCallback(
                static fn(): string => $inner->getContents(),
            );
        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                'POST',
                'https://example.test/',
            )->withBody($nonSeekable),
        );
        $collector->collectResponse(HelperFactory::createResponse());

        self::assertSame(
            'non-seekable-body',
            $nonSeekable->getContents(),
            'A non-seekable body must remain untouched for downstream consumers.',
        );
        self::assertSame(
            [],
            $collector->capture()?->data()['requestBody'] ?? null,
            'A body that cannot be read safely must not be captured.',
        );
    }

    public function testCollectRequestRequiresAnActiveLifecycle(): void
    {
        $collector = new RequestCollector();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'must be started before collecting a request',
        );

        $collector->collectRequest(HelperFactory::createRequest(uri: 'https://example.test/'));
    }

    public function testCollectResponseRequiresAnActiveLifecycle(): void
    {
        $collector = new RequestCollector();

        $collector->startup();
        $collector->shutdown();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'must be started before collecting a response',
        );

        $collector->collectResponse(HelperFactory::createResponse());
    }

    public function testShutdownClearsAllRequestScopedState(): void
    {
        $collector = new RequestCollector();

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(uri: 'https://example.test/first')
                ->withQueryParams(['first' => 'value']),
        );
        $collector->collectResponse(HelperFactory::createResponse(201, ['X-First' => 'value']));

        self::assertNotNull(
            $collector->capture(),
            'The first lifecycle must produce a snapshot.',
        );

        $collector->shutdown();
        $collector->shutdown();

        self::assertNull(
            $collector->capture(),
            'Shutdown must deactivate and clear the collector idempotently.',
        );

        $collector->startup();
        $collector->collectRequest(HelperFactory::createRequest(uri: 'https://example.test/second'));

        self::assertNull(
            $collector->capture(),
            'A new request must not inherit the previous response.',
        );

        $collector->collectResponse(HelperFactory::createResponse(204));

        $data = $collector->capture()?->data() ?? [];

        self::assertSame(
            [],
            $data['GET'] ?? null,
            'A new lifecycle must not inherit previous query parameters.',
        );
        self::assertSame(
            204,
            $data['statusCode'] ?? null,
            'A new lifecycle must capture its own response status.',
        );

        $responseHeaders = $data['responseHeaders'] ?? null;

        self::assertIsArray(
            $responseHeaders,
            'Response headers must retain their array shape.',
        );
        self::assertArrayNotHasKey(
            'X-First',
            $responseHeaders,
            'A new lifecycle must not inherit previous response headers.',
        );
    }

    public function testStartupIsIdempotentAndPreservesNonStringServerEntries(): void
    {
        $collector = new RequestCollector();

        $collector->startup();
        $collector->collectRequest(
            HelperFactory::createRequest(
                uri: 'https://example.test/',
                serverParams: [
                    'SERVER_PORT' => 443,
                ],
            ),
        );
        $collector->collectResponse(HelperFactory::createResponse());
        $collector->startup();
        $data = $collector->capture()?->data() ?? [];

        self::assertSame(
            [
                'SERVER_PORT' => 443,
            ],
            $data['SERVER'] ?? null,
            'Repeated startup calls must preserve the active request and non-string server entries.',
        );
    }
}
