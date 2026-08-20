<?php

declare(strict_types=1);

namespace Yii3\Debug\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yii3\Debug\Web\CsrfRequestValidator;
use Yiisoft\Csrf\{CsrfTokenInterface, StubCsrfToken};
use Yiisoft\Di\{Container, ContainerConfig, NotFoundException};

/**
 * Verifies that user-switch DI fails closed when CSRF protection is enabled but unavailable.
 */
final class CsrfConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool, bool, bool}>
     */
    public static function configurationProvider(): iterable
    {
        yield 'disabled without service' => [false, false, true, false];
        yield 'disabled with service' => [false, true, true, true];
        yield 'enabled without service' => [true, false, false, false];
        yield 'enabled with service' => [true, true, true, true];
    }

    #[DataProvider('configurationProvider')]
    public function testCsrfServiceMatrix(
        bool $enabled,
        bool $registered,
        bool $resolves,
        bool $validates,
    ): void {
        $params = require dirname(__DIR__) . '/config/params.php';
        self::assertIsArray($params, 'Package parameters must return an array.');

        $debug = $params['yii3/debug'] ?? null;
        self::assertIsArray($debug, 'Debug parameters must be present.');

        $userSwitch = $debug['userSwitch'] ?? null;
        self::assertIsArray($userSwitch, 'User-switch parameters must be present.');

        $userSwitch['enabled'] = $enabled;
        $debug['userSwitch'] = $userSwitch;
        $params['yii3/debug'] = $debug;

        $definitions = require dirname(__DIR__) . '/config/di-panels.php';
        self::assertIsArray($definitions, 'Panel definitions must return an array.');

        if ($registered) {
            $definitions[CsrfTokenInterface::class] = new StubCsrfToken('valid');
        }

        $container = new Container(ContainerConfig::create()->withDefinitions($definitions));

        if (!$resolves) {
            $this->expectException(NotFoundException::class);
        }

        $validator = $container->get(CsrfRequestValidator::class);
        self::assertInstanceOf(CsrfRequestValidator::class, $validator, 'CSRF validator must resolve.');
        $request = (new ServerRequest('POST', '/'))->withParsedBody(['_csrf' => 'valid']);

        self::assertSame($validates, $validator->validates($request), 'Resolved validator must match the CSRF matrix.');
    }
}
