<?php

declare(strict_types=1);

namespace Inspirum\Balikobot\Integration\Laravel\Tests;

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

final class Service
{
    public function __construct(
        public readonly Client $client,
        public readonly DefaultClient $defaultClient,
        public readonly DefaultCurlRequester $defaultCurlRequester,
        public readonly Requester $requester,
        public readonly Validator $validator,
        public readonly AccountFactory $accountFactory,
        public readonly DefaultAccountFactory $defaultAccountFactory,
        public readonly AdrUnitFactory $adrUnitFactory,
        public readonly DefaultAdrUnitFactory $defaultAdrUnitFactory,
        public readonly AttributeFactory $attributeFactory,
        public readonly DefaultAttributeFactory $defaultAttributeFactory,
        public readonly BranchFactory $branchFactory,
        public readonly DefaultBranchFactory $defaultBranchFactory,
        public readonly BranchResolver $branchResolver,
        public readonly DefaultBranchResolver $defaultBranchResolver,
        public readonly CarrierFactory $carrierFactory,
        public readonly DefaultCarrierFactory $defaultCarrierFactory,
        public readonly ChangelogFactory $changelogFactory,
        public readonly DefaultChangelogFactory $defaultChangelogFactory,
        public readonly CountryFactory $countryFactory,
        public readonly DefaultCountryFactory $defaultCountryFactory,
        public readonly LabelFactory $labelFactory,
        public readonly DefaultLabelFactory $defaultLabelFactory,
        public readonly ManipulationUnitFactory $manipulationUnitFactory,
        public readonly DefaultManipulationUnitFactory $defaultManipulationUnitFactory,
        public readonly MethodFactory $methodFactory,
        public readonly DefaultMethodFactory $defaultMethodFactory,
        public readonly OrderedShipmentFactory $orderedShipmentFactory,
        public readonly DefaultOrderedShipmentFactory $defaultOrderedShipmentFactory,
        public readonly PackageFactory $packageFactory,
        public readonly DefaultPackageFactory $defaultPackageFactory,
        public readonly PackageDataFactory $packageDataFactory,
        public readonly DefaultPackageDataFactory $defaultPackageDataFactory,
        public readonly ProofOfDeliveryFactory $proofOfDeliveryFactory,
        public readonly DefaultProofOfDeliveryFactory $defaultProofOfDeliveryFactory,
        public readonly ServiceFactory $serviceFactory,
        public readonly DefaultServiceFactory $defaultServiceFactory,
        public readonly StatusFactory $statusFactory,
        public readonly DefaultStatusFactory $defaultStatusFactory,
        public readonly TransportCostFactory $transportCostFactory,
        public readonly DefaultTransportCostFactory $defaultTransportCostFactory,
        public readonly ZipCodeFactory $zipCodeFactory,
        public readonly DefaultZipCodeFactory $defaultZipCodeFactory,
        public readonly CarrierProvider $carrierProvider,
        public readonly DefaultCarrierProvider $defaultCarrierProvider,
        public readonly LiveCarrierProvider $liveCarrierProvider,
        public readonly ServiceProvider $serviceProvider,
        public readonly DefaultServiceProvider $defaultServiceProvider,
        public readonly LiveServiceProvider $liveServiceProvider,
        public readonly BranchService $branchService,
        public readonly DefaultBranchService $defaultBranchService,
        public readonly InfoService $infoService,
        public readonly DefaultInfoService $defaultInfoService,
        public readonly PackageService $packageService,
        public readonly DefaultPackageService $defaultPackageService,
        public readonly SettingService $settingService,
        public readonly DefaultSettingService $defaultSettingService,
        public readonly TrackService $trackService,
        public readonly DefaultTrackService $defaultTrackService,
        public readonly ServiceContainer $serviceContainer,
        public readonly DefaultServiceContainer $defaultServiceContainer,
        public readonly ServiceContainerRegistry $serviceContainerRegistry,
        public readonly DefaultServiceContainerRegistry $defaultServiceContainerRegistry,
    ) {
    }
}
