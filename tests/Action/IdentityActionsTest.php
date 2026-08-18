<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests\Action;

use GuzzleHttp\Psr7\{HttpFactory, ServerRequest};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Action\{ResetIdentityAction, SetIdentityAction};
use Yii3\Debug\Tests\Support\{FakeIdentity, UserFixture};
use Yii3\Debug\Web\{LocalAccessChecker, ResponseBuilder};

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Unit tests for {@see SetIdentityAction} and {@see ResetIdentityAction} guarding the user switch.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class IdentityActionsTest extends TestCase
{
    public function testResetIdentityDeniesWhenSwitchingIsDisabled(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        $action = new ResetIdentityAction(new LocalAccessChecker(), $this->responseBuilder(), $fixture->userSwitch);

        $response = $action($this->request([]));

        self::assertSame(403, $response->getStatusCode(), 'Deny-by-default must reject the reset.');
        self::assertSame('2', $fixture->currentUser->getId(), 'Impersonation must stay in place.');
    }

    public function testResetIdentityRestoresMainUser(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));
        $fixture->userSwitch->setUser(new FakeIdentity('2'));

        $action = new ResetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            true,
        );

        $response = $action($this->request([]));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode(), 'Allowed reset must succeed.');
        self::assertIsArray($payload, 'Response body must decode to an object.');
        self::assertSame('1', $payload['id'] ?? null, 'Response must expose the restored main ID.');
        self::assertSame('1', $fixture->currentUser->getId(), 'Main identity must be restored.');
    }

    public function testSetIdentityDeniesDisallowedClientIp(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $action = new SetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            $fixture->repository,
            true,
        );

        $response = $action($this->request(['user_id' => '2'], '203.0.113.7'));

        self::assertSame(403, $response->getStatusCode(), 'Remote client must be denied.');
    }

    public function testSetIdentityDeniesGuestsEvenWhenEnabled(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('2')]);
        $action = new SetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            $fixture->repository,
            true,
        );

        $response = $action($this->request(['user_id' => '2']));

        self::assertSame(403, $response->getStatusCode(), 'Guest request must be denied.');
        self::assertNull($fixture->currentUser->getId(), 'Guest must stay unauthenticated.');
    }

    public function testSetIdentityDeniesWhenSwitchingIsDisabled(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $action = new SetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            $fixture->repository,
        );

        $response = $action($this->request(['user_id' => '2']));

        self::assertSame(403, $response->getStatusCode(), 'Deny-by-default must reject the switch.');
        self::assertSame('1', $fixture->currentUser->getId(), 'Identity must stay unchanged.');
    }

    public function testSetIdentityRejectsMissingUserIdParameter(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $action = new SetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            $fixture->repository,
            true,
        );

        $response = $action($this->request([]));

        self::assertSame(400, $response->getStatusCode(), 'Missing `user_id` must yield a validation error.');
    }

    public function testSetIdentityReportsUnknownUser(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $action = new SetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            $fixture->repository,
            true,
        );

        $response = $action($this->request(['user_id' => '404']));

        self::assertSame(404, $response->getStatusCode(), 'Unknown identity must yield not-found.');
    }
    public function testSetIdentitySwitchesUserForAllowedAuthenticatedRequest(): void
    {
        $fixture = UserFixture::create([new FakeIdentity('1'), new FakeIdentity('2')]);

        $fixture->currentUser->login(new FakeIdentity('1'));

        $action = new SetIdentityAction(
            new LocalAccessChecker(),
            $this->responseBuilder(),
            $fixture->userSwitch,
            $fixture->repository,
            true,
        );

        $response = $action($this->request(['user_id' => '2']));
        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode(), 'Allowed switch must succeed.');
        self::assertIsArray($payload, 'Response body must decode to an object.');
        self::assertSame('2', $payload['id'] ?? null, 'Response must expose the impersonated ID.');
        self::assertSame('2', $fixture->currentUser->getId(), 'Identity must be switched.');
        self::assertFalse($fixture->userSwitch->isMainUser(), 'Impersonation must be tracked.');
    }

    /**
     * Creates a POST request with the given parsed body.
     *
     * @param array<string, string> $body Parsed body parameters.
     * @param string $ip Client IP address.
     *
     * @return ServerRequest Configured request.
     */
    private function request(array $body, string $ip = '127.0.0.1'): ServerRequest
    {
        return (new ServerRequest(
            'POST',
            'https://example.test/debug/set-identity',
            serverParams: ['REMOTE_ADDR' => $ip],
        ))->withParsedBody($body);
    }

    /**
     * Creates the JSON response factory.
     *
     * @return ResponseBuilder Configured response builder.
     */
    private function responseBuilder(): ResponseBuilder
    {
        $factory = new HttpFactory();

        return new ResponseBuilder($factory, $factory);
    }
}
