<?php

declare(strict_types=1);

namespace Inspirum\Balikobot\Integration\Laravel\Tests;

use Illuminate\Contracts\Console\Kernel;
use Inspirum\Balikobot\Client\Client;
use Inspirum\Balikobot\Client\Requester;
use Inspirum\Balikobot\Exception\ServiceContainerNotFoundException;
use Inspirum\Balikobot\Integration\Laravel\BalikobotServiceProvider;
use Inspirum\Balikobot\Service\BranchService;
use Inspirum\Balikobot\Service\InfoService;
use Inspirum\Balikobot\Service\PackageService;
use Inspirum\Balikobot\Service\Registry\ServiceContainer;
use Inspirum\Balikobot\Service\SettingService;
use Inspirum\Balikobot\Service\TrackService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class BalikobotPackageTest extends TestCase
{
    public function testBundleNoConfig(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Missing "api_key" configuration');

        $this->bootKernel('balikobot_missing', static function (Service $service): void {
        });
    }

    public function testBundle(): void
    {
        $this->bootKernel('balikobot', static function (Service $service): void {
            self::assertRequester('testUser', 'testKey1', $service->requester);
            self::assertServiceRequester('testUser', 'testKey1', $service->branchService);
            self::assertServiceRequester('testUser', 'testKey1', $service->infoService);
            self::assertServiceRequester('testUser', 'testKey1', $service->packageService);
            self::assertServiceRequester('testUser', 'testKey1', $service->settingService);
            self::assertServiceRequester('testUser', 'testKey1', $service->trackService);

            self::assertServiceContainerRequester('testUser', 'testKey1', $service->serviceContainer);
        });
    }

    public function testBundleMulti(): void
    {
        $this->bootKernel('balikobot_multi', static function (Service $service): void {
            self::assertRequester('testUser2', 'testKey2', $service->defaultCurlRequester);

            self::assertServiceContainerRequester('testUser2', 'testKey2', $service->serviceContainer);

            self::assertSame($service->serviceContainer, $service->serviceContainerRegistry->get());
            self::assertSame($service->serviceContainerRegistry->get('client2'), $service->serviceContainerRegistry->get());

            self::assertServiceContainerRequester('testUser1', 'testKey1', $service->serviceContainerRegistry->get('client1'));
        });
    }

    public function testBundleMissing(): void
    {
        self::expectException(ServiceContainerNotFoundException::class);

        $this->bootKernel('balikobot_multi', static function (Service $service): void {
            $service->serviceContainerRegistry->get('client3');
        });
    }

    public function testBundleMultiDefault(): void
    {
        $this->bootKernel('balikobot_multi_default', static function (Service $service): void {
            self::assertRequester('testUser3', 'testKey3', $service->defaultCurlRequester);

            self::assertSame($service->serviceContainerRegistry->get('default'), $service->serviceContainerRegistry->get());

            self::assertServiceContainerRequester('testUser3', 'testKey3', $service->serviceContainer);
            self::assertServiceContainerRequester('testUser3', 'testKey3', $service->serviceContainerRegistry->get());
            self::assertServiceContainerRequester('testUser1', 'testKey1', $service->serviceContainerRegistry->get('client1'));
            self::assertServiceContainerRequester('testUser2', 'testKey2', $service->serviceContainerRegistry->get('client2'));
        });
    }

    public function testBundleCustomDefault(): void
    {
        $this->bootKernel('balikobot_multi_custom_default', static function (Service $service): void {
            self::assertRequester('testUser2', 'testKey2', $service->defaultCurlRequester);

            self::assertSame($service->serviceContainerRegistry->get('client2'), $service->serviceContainerRegistry->get());

            self::assertServiceContainerRequester('testUser2', 'testKey2', $service->serviceContainer);
            self::assertServiceContainerRequester('testUser2', 'testKey2', $service->serviceContainerRegistry->get());
            self::assertServiceContainerRequester('testUser1', 'testKey1', $service->serviceContainerRegistry->get('client1'));
            self::assertServiceContainerRequester('testUser2', 'testKey2', $service->serviceContainerRegistry->get('client2'));
            self::assertServiceContainerRequester('testUser3', 'testKey3', $service->serviceContainerRegistry->get('default'));
        });
    }

    /**
     * @param callable(\Inspirum\Balikobot\Integration\Laravel\Tests\Service): void $cb
     */
    private function bootKernel(string $package, callable $cb): void
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = require __DIR__ . '/../vendor/laravel/laravel/bootstrap/app.php';

        /** @var \Illuminate\Contracts\Console\Kernel $kernel */
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $app['config'];

        $config->set('balikobot', require __DIR__ . '/config/' . $package . '.php');

        $app->register(BalikobotServiceProvider::class);

        $service = $app->make(Service::class);

        self::assertInstanceOf(Service::class, $service);

        $cb($service);
    }

    private static function assertRequester(string $expectedUser, string $expectedKey, Requester $requester): void
    {
        self::assertSame($expectedUser, self::getProperty($requester, 'apiUser'));
        self::assertSame($expectedKey, self::getProperty($requester, 'apiKey'));
    }

    private static function assertServiceRequester(string $expectedUser, string $expectedKey, BranchService|InfoService|PackageService|SettingService|TrackService $service): void
    {
        $client = self::getProperty($service, 'client');
        self::assertInstanceOf(Client::class, $client);
        $requester = self::getProperty($client, 'requester');
        self::assertInstanceOf(Requester::class, $requester);

        self::assertSame($expectedUser, self::getProperty($requester, 'apiUser'));
        self::assertSame($expectedKey, self::getProperty($requester, 'apiKey'));
    }

    private static function assertServiceContainerRequester(string $expectedUser, string $expectedKey, ServiceContainer $container): void
    {
        self::assertServiceRequester($expectedUser, $expectedKey, $container->getBranchService());
        self::assertServiceRequester($expectedUser, $expectedKey, $container->getInfoService());
        self::assertServiceRequester($expectedUser, $expectedKey, $container->getPackageService());
        self::assertServiceRequester($expectedUser, $expectedKey, $container->getSettingService());
        self::assertServiceRequester($expectedUser, $expectedKey, $container->getTrackService());
    }

    private static function getProperty(object $service, string $key): mixed
    {
        $requester = new ReflectionClass($service);
        $property  = $requester->getProperty($key);

        return $property->getValue($service);
    }
}
