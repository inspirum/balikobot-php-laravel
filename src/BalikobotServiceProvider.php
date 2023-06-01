<?php

declare(strict_types=1);

namespace Inspirum\Balikobot\Integration\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Inspirum\Balikobot\Client\Client;
use Inspirum\Balikobot\Client\DefaultClient;
use Inspirum\Balikobot\Client\DefaultCurlRequester;
use Inspirum\Balikobot\Client\Requester;
use Inspirum\Balikobot\Client\Response\Validator;
use Inspirum\Balikobot\Model\Account\AccountFactory;
use Inspirum\Balikobot\Model\Account\DefaultAccountFactory;
use Inspirum\Balikobot\Model\AdrUnit\AdrUnitFactory;
use Inspirum\Balikobot\Model\AdrUnit\DefaultAdrUnitFactory;
use Inspirum\Balikobot\Model\Attribute\AttributeFactory;
use Inspirum\Balikobot\Model\Attribute\DefaultAttributeFactory;
use Inspirum\Balikobot\Model\Branch\BranchFactory;
use Inspirum\Balikobot\Model\Branch\BranchResolver;
use Inspirum\Balikobot\Model\Branch\DefaultBranchFactory;
use Inspirum\Balikobot\Model\Branch\DefaultBranchResolver;
use Inspirum\Balikobot\Model\Carrier\CarrierFactory;
use Inspirum\Balikobot\Model\Carrier\DefaultCarrierFactory;
use Inspirum\Balikobot\Model\Changelog\ChangelogFactory;
use Inspirum\Balikobot\Model\Changelog\DefaultChangelogFactory;
use Inspirum\Balikobot\Model\Country\CountryFactory;
use Inspirum\Balikobot\Model\Country\DefaultCountryFactory;
use Inspirum\Balikobot\Model\Label\DefaultLabelFactory;
use Inspirum\Balikobot\Model\Label\LabelFactory;
use Inspirum\Balikobot\Model\ManipulationUnit\DefaultManipulationUnitFactory;
use Inspirum\Balikobot\Model\ManipulationUnit\ManipulationUnitFactory;
use Inspirum\Balikobot\Model\Method\DefaultMethodFactory;
use Inspirum\Balikobot\Model\Method\MethodFactory;
use Inspirum\Balikobot\Model\OrderedShipment\DefaultOrderedShipmentFactory;
use Inspirum\Balikobot\Model\OrderedShipment\OrderedShipmentFactory;
use Inspirum\Balikobot\Model\Package\DefaultPackageFactory;
use Inspirum\Balikobot\Model\Package\PackageFactory;
use Inspirum\Balikobot\Model\PackageData\DefaultPackageDataFactory;
use Inspirum\Balikobot\Model\PackageData\PackageDataFactory;
use Inspirum\Balikobot\Model\ProofOfDelivery\DefaultProofOfDeliveryFactory;
use Inspirum\Balikobot\Model\ProofOfDelivery\ProofOfDeliveryFactory;
use Inspirum\Balikobot\Model\Service\DefaultServiceFactory;
use Inspirum\Balikobot\Model\Service\ServiceFactory;
use Inspirum\Balikobot\Model\Status\DefaultStatusFactory;
use Inspirum\Balikobot\Model\Status\StatusFactory;
use Inspirum\Balikobot\Model\TransportCost\DefaultTransportCostFactory;
use Inspirum\Balikobot\Model\TransportCost\TransportCostFactory;
use Inspirum\Balikobot\Model\ZipCode\DefaultZipCodeFactory;
use Inspirum\Balikobot\Model\ZipCode\ZipCodeFactory;
use Inspirum\Balikobot\Provider\CarrierProvider;
use Inspirum\Balikobot\Provider\DefaultCarrierProvider;
use Inspirum\Balikobot\Provider\DefaultServiceProvider;
use Inspirum\Balikobot\Provider\LiveCarrierProvider;
use Inspirum\Balikobot\Provider\LiveServiceProvider;
use Inspirum\Balikobot\Provider\ServiceProvider;
use Inspirum\Balikobot\Service\BranchService;
use Inspirum\Balikobot\Service\DefaultBranchService;
use Inspirum\Balikobot\Service\DefaultInfoService;
use Inspirum\Balikobot\Service\DefaultPackageService;
use Inspirum\Balikobot\Service\DefaultSettingService;
use Inspirum\Balikobot\Service\DefaultTrackService;
use Inspirum\Balikobot\Service\InfoService;
use Inspirum\Balikobot\Service\PackageService;
use Inspirum\Balikobot\Service\Registry\DefaultServiceContainer;
use Inspirum\Balikobot\Service\Registry\DefaultServiceContainerRegistry;
use Inspirum\Balikobot\Service\Registry\ServiceContainer;
use Inspirum\Balikobot\Service\Registry\ServiceContainerRegistry;
use Inspirum\Balikobot\Service\SettingService;
use Inspirum\Balikobot\Service\TrackService;
use RuntimeException;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function config_path;
use function sprintf;

final class BalikobotServiceProvider extends LaravelServiceProvider
{
    public const ALIAS = 'balikobot';

    private const CONNECTION_DEFAULT = 'default';

    /**
     * @return array{'default'?: string, 'connections'?: array<string,array{'api_user': string, 'api_key': string}>}
     */
    private function getConfig(Application $app): array
    {
        return $app['config']['balikobot'] ?? [];
    }

    public function register(): void
    {
        $app = $this->app;

        $this->mergeConfigFrom(self::configFilepath(), self::ALIAS);

        $config                = $this->getConfig($app);
        $connections           = $this->resolveConnections($config);
        $connectionsNames      = array_keys($connections);
        $defaultConnectionName = $this->resolveDefaultConnectionName($connections, $config['default'] ?? self::CONNECTION_DEFAULT);

        $this->registerClients($app, $connections, $defaultConnectionName);
        $this->registerFactories($app);
        $this->registerProviders($app);
        $this->registerServices($app, $connectionsNames, $defaultConnectionName);
        $this->registerServiceRegistry($app, $connectionsNames, $defaultConnectionName);
    }

    private static function configFilepath(): string
    {
        return sprintf('%s/../config/%s', __DIR__, self::configFilename());
    }

    private static function configFilename(): string
    {
        return sprintf('%s.php', self::ALIAS);
    }

    public function boot(): void
    {
        $this->publishes([self::configFilepath() => config_path(self::configFilename())], 'config');
    }

    /**
     * @param array<string, array{'api_user': string, 'api_key': string}> $connections
     */
    private function registerClients(Application $app, array $connections, string $defaultConnectionName): void
    {
        $app->singleton(Validator::class);

        foreach ($connections as $name => $connection) {
            $this->registerClientForConnection($app, $connection, $name, $name === $defaultConnectionName);
        }
    }

    /**
     * @param array{'api_user'?: string, 'api_key'?: string} $connection
     */
    private function registerClientForConnection(Application $app, array $connection, string $name, bool $default): void
    {
        $defaultCurlRequesterServiceId = $this->serviceIdForConnection(DefaultCurlRequester::class, $name);
        $app->singleton($defaultCurlRequesterServiceId, static fn() => new DefaultCurlRequester(
            $connection['api_user'] ?? throw new RuntimeException('Missing "api_user" configuration'),
            $connection['api_key'] ?? throw new RuntimeException('Missing "api_key" configuration'),
        ));

        $requesterServiceId = $this->serviceIdForConnection(Requester::class, $name);
        $app->alias($defaultCurlRequesterServiceId, $requesterServiceId);

        $defaultClientServiceId = $this->serviceIdForConnection(DefaultClient::class, $name);
        $app->singleton($defaultClientServiceId, static fn() => new DefaultClient(
            $app->get($requesterServiceId),
            $app->get(Validator::class),
        ));

        $clientServiceId = $this->serviceIdForConnection(Client::class, $name);
        $app->alias($defaultClientServiceId, $clientServiceId);

        if ($default) {
            $app->alias($defaultCurlRequesterServiceId, DefaultCurlRequester::class);
            $app->alias($defaultCurlRequesterServiceId, Requester::class);
            $app->alias($defaultClientServiceId, DefaultClient::class);
            $app->alias($defaultClientServiceId, Client::class);
        }
    }

    private function registerFactories(Application $app): void
    {
        $app->singleton(DefaultAccountFactory::class);
        $app->alias(DefaultAccountFactory::class, AccountFactory::class);

        $app->singleton(DefaultAdrUnitFactory::class);
        $app->alias(DefaultAdrUnitFactory::class, AdrUnitFactory::class);

        $app->singleton(DefaultAttributeFactory::class);
        $app->alias(DefaultAttributeFactory::class, AttributeFactory::class);

        $app->singleton(DefaultBranchFactory::class);
        $app->alias(DefaultBranchFactory::class, BranchFactory::class);

        $app->singleton(DefaultBranchResolver::class);
        $app->alias(DefaultBranchResolver::class, BranchResolver::class);

        $app->singleton(DefaultCarrierFactory::class);
        $app->alias(DefaultCarrierFactory::class, CarrierFactory::class);

        $app->singleton(DefaultChangelogFactory::class);
        $app->alias(DefaultChangelogFactory::class, ChangelogFactory::class);

        $app->singleton(DefaultCountryFactory::class);
        $app->alias(DefaultCountryFactory::class, CountryFactory::class);

        $app->singleton(DefaultLabelFactory::class);
        $app->alias(DefaultLabelFactory::class, LabelFactory::class);

        $app->singleton(DefaultManipulationUnitFactory::class);
        $app->alias(DefaultManipulationUnitFactory::class, ManipulationUnitFactory::class);

        $app->singleton(DefaultMethodFactory::class);
        $app->alias(DefaultMethodFactory::class, MethodFactory::class);

        $app->singleton(DefaultOrderedShipmentFactory::class);
        $app->alias(DefaultOrderedShipmentFactory::class, OrderedShipmentFactory::class);

        $app->singleton(DefaultPackageFactory::class);
        $app->alias(DefaultPackageFactory::class, PackageFactory::class);

        $app->singleton(DefaultPackageDataFactory::class);
        $app->alias(DefaultPackageDataFactory::class, PackageDataFactory::class);

        $app->singleton(DefaultProofOfDeliveryFactory::class);
        $app->alias(DefaultProofOfDeliveryFactory::class, ProofOfDeliveryFactory::class);

        $app->singleton(DefaultServiceFactory::class);
        $app->alias(DefaultServiceFactory::class, ServiceFactory::class);

        $app->singleton(DefaultStatusFactory::class);
        $app->alias(DefaultStatusFactory::class, StatusFactory::class);

        $app->singleton(DefaultTransportCostFactory::class);
        $app->alias(DefaultTransportCostFactory::class, TransportCostFactory::class);

        $app->singleton(DefaultZipCodeFactory::class);
        $app->alias(DefaultZipCodeFactory::class, ZipCodeFactory::class);
    }

    private function registerProviders(Application $app): void
    {
        $app->singleton(DefaultCarrierProvider::class);
        $app->singleton(LiveCarrierProvider::class);
        $app->alias(DefaultCarrierProvider::class, CarrierProvider::class);

        $app->singleton(DefaultServiceProvider::class);
        $app->singleton(LiveServiceProvider::class);
        $app->alias(DefaultServiceProvider::class, ServiceProvider::class);
    }

    /**
     * @param array<string> $connectionsNames
     */
    private function registerServices(Application $app, array $connectionsNames, string $defaultConnectionName): void
    {
        foreach ($connectionsNames as $name) {
            $this->registerServicesForConnection($app, $name, $name === $defaultConnectionName);
        }
    }

    private function registerServicesForConnection(Application $app, string $name, bool $default): void
    {
        $clientServiceId = $this->serviceIdForConnection(Client::class, $name);

        $defaultBranchServiceServiceId = $this->serviceIdForConnection(DefaultBranchService::class, $name);
        $app->singleton($defaultBranchServiceServiceId, static fn() => new DefaultBranchService(
            $app->get($clientServiceId),
            $app->get(BranchFactory::class),
            $app->get(BranchResolver::class),
            $app->get(CarrierProvider::class),
            $app->get(ServiceProvider::class),
        ));

        $branchServiceServiceId = $this->serviceIdForConnection(BranchService::class, $name);
        $app->alias($defaultBranchServiceServiceId, $branchServiceServiceId);

        $defaultInfoServiceServiceId = $this->serviceIdForConnection(DefaultInfoService::class, $name);
        $app->singleton($defaultInfoServiceServiceId, static fn() => new DefaultInfoService(
            $app->get($clientServiceId),
            $app->get(AccountFactory::class),
            $app->get(ChangelogFactory::class),
        ));

        $infoServiceServiceId = $this->serviceIdForConnection(InfoService::class, $name);
        $app->alias($defaultInfoServiceServiceId, $infoServiceServiceId);

        $defaultPackageServiceServiceId = $this->serviceIdForConnection(DefaultPackageService::class, $name);
        $app->singleton($defaultPackageServiceServiceId, static fn() => new DefaultPackageService(
            $app->get($clientServiceId),
            $app->get(PackageDataFactory::class),
            $app->get(PackageFactory::class),
            $app->get(OrderedShipmentFactory::class),
            $app->get(LabelFactory::class),
            $app->get(ProofOfDeliveryFactory::class),
            $app->get(TransportCostFactory::class),
        ));

        $packageServiceServiceId = $this->serviceIdForConnection(PackageService::class, $name);
        $app->alias($defaultPackageServiceServiceId, $packageServiceServiceId);

        $defaultSettingServiceServiceId = $this->serviceIdForConnection(DefaultSettingService::class, $name);
        $app->singleton($defaultSettingServiceServiceId, static fn() => new DefaultSettingService(
            $app->get($clientServiceId),
            $app->get(CarrierFactory::class),
            $app->get(ServiceFactory::class),
            $app->get(ManipulationUnitFactory::class),
            $app->get(CountryFactory::class),
            $app->get(ZipCodeFactory::class),
            $app->get(AdrUnitFactory::class),
            $app->get(AttributeFactory::class),
        ));

        $settingServiceServiceId = $this->serviceIdForConnection(SettingService::class, $name);
        $app->alias($defaultSettingServiceServiceId, $settingServiceServiceId);

        $defaultTrackServiceServiceId = $this->serviceIdForConnection(DefaultTrackService::class, $name);
        $app->singleton($defaultTrackServiceServiceId, static fn() => new DefaultTrackService(
            $app->get($clientServiceId),
            $app->get(StatusFactory::class),
        ));

        $tackServiceServiceId = $this->serviceIdForConnection(TrackService::class, $name);
        $app->alias($defaultTrackServiceServiceId, $tackServiceServiceId);

        if ($default) {
            $app->alias($defaultBranchServiceServiceId, BranchService::class);
            $app->alias($defaultBranchServiceServiceId, DefaultBranchService::class);
            $app->alias($defaultInfoServiceServiceId, InfoService::class);
            $app->alias($defaultInfoServiceServiceId, DefaultInfoService::class);
            $app->alias($defaultPackageServiceServiceId, PackageService::class);
            $app->alias($defaultPackageServiceServiceId, DefaultPackageService::class);
            $app->alias($defaultSettingServiceServiceId, SettingService::class);
            $app->alias($defaultSettingServiceServiceId, DefaultSettingService::class);
            $app->alias($defaultTrackServiceServiceId, TrackService::class);
            $app->alias($defaultTrackServiceServiceId, DefaultTrackService::class);
        }
    }

    /**
     * @param array<string> $connectionsNames
     */
    private function registerServiceRegistry(Application $app, array $connectionsNames, string $defaultConnectionName): void
    {
        $containers = [];

        foreach ($connectionsNames as $name) {
            $this->registerServiceContainerForConnection($app, $name, $name === $defaultConnectionName);

            $containers[$name] = $app->get($this->serviceIdForConnection(ServiceContainer::class, $name));
        }

        $app->singleton(DefaultServiceContainerRegistry::class, static fn() => new DefaultServiceContainerRegistry(
            $containers,
            $defaultConnectionName,
        ));

        $app->alias(DefaultServiceContainerRegistry::class, ServiceContainerRegistry::class);
    }

    private function registerServiceContainerForConnection(Application $app, string $name, bool $default): void
    {
        $defaultServiceContainerServiceId = $this->serviceIdForConnection(DefaultServiceContainer::class, $name);
        $app->singleton($defaultServiceContainerServiceId, fn() => new DefaultServiceContainer(
            $app->get($this->serviceIdForConnection(BranchService::class, $name)),
            $app->get($this->serviceIdForConnection(InfoService::class, $name)),
            $app->get($this->serviceIdForConnection(PackageService::class, $name)),
            $app->get($this->serviceIdForConnection(SettingService::class, $name)),
            $app->get($this->serviceIdForConnection(TrackService::class, $name)),
        ));

        $serviceContainerServiceId = $this->serviceIdForConnection(ServiceContainer::class, $name);
        $app->alias($defaultServiceContainerServiceId, $serviceContainerServiceId);

        if ($default) {
            $app->alias($defaultServiceContainerServiceId, DefaultServiceContainer::class);
            $app->alias($defaultServiceContainerServiceId, ServiceContainer::class);
        }
    }

    /**
     * @param array<string,array{'api_user': string, 'api_key': string}> $connections
     */
    private function resolveDefaultConnectionName(array $connections, string $defaultName): string
    {
        if (array_key_exists($defaultName, $connections)) {
            return $defaultName;
        }

        return array_key_first($connections) ?? self::CONNECTION_DEFAULT;
    }

    /**
     * @param array{'api_user'?: string, 'api_key'?: string, 'connections'?: array<string,array{'api_user': string, 'api_key': string}>} $config
     *
     * @return array<string,array{'api_user': string, 'api_key': string}>
     */
    private function resolveConnections(array $config): array
    {
        return $config['connections'] ?? [];
    }

    private function serviceIdForConnection(string $class, string $name): string
    {
        return sprintf('%s.%s', $class, $name);
    }
}
