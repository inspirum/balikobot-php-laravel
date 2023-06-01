# Balikobot Laravel Package

[![Latest Stable Version][ico-packagist-stable]][link-packagist-stable]
[![Build Status][ico-workflow]][link-workflow]
[![PHPStan][ico-phpstan]][link-phpstan]
[![Total Downloads][ico-packagist-download]][link-packagist-download]
[![Software License][ico-license]][link-licence]

Laravel integration for [`inspirum/balikobot`][link-balikobot].

## Installation

Run composer require command:
```
composer require inspirum/balikobot-laravel
```

Laravel will automatically register via [Package Discovery](https://laravel.com/docs/master/packages#package-discovery).

If you [opt out](https://laravel.com/docs/master/packages#opting-out-of-package-discovery) from package discovery it is necessary to register the service provider in `config/app.php`.

```php
<?php

return [
    // ...
    'providers' => [
        // ...
        Inspirum\Balikobot\Integration\Laravel\BalikobotServiceProvider::class,
    ],
];
```


### Config Files

In order to edit the default configuration you may execute:

```shell script
php artisan vendor:publish --provider="Inspirum\Balikobot\Integration\Laravel\BalikobotServiceProvider"
```

After that, configure client credentials in `config/balikobot.php` or by setting the env variables:

```ini
BALIKOBOT_API_USER='api_user'
BALIKOBOT_API_KEY='api_key'
```


## Usage

Use `ServiceContainerRegistry` to get `ServiceContainer` for given connection.

```php
/** @var Inspirum\Balikobot\Service\Registry\ServiceContainerRegistry $registry */

// get package service for default (or first) connection
$packageService = $registry->get()->getPackageService();

// get branch service for "client3" connection
$packageService = $registry->get('client3')->getBranchService();
```

or use services directly for default connection

```php
/** @var Inspirum\Balikobot\Service\PackageService $packageService */
$packageService->addPackages(...)

/** @var Inspirum\Balikobot\Service\BranchService $branchService */
$branchService->getBranches(...)
```


## Contributing

Please see [CONTRIBUTING][link-contributing] and [CODE_OF_CONDUCT][link-code-of-conduct] for details.


## Security

If you discover any security related issues, please email tomas.novotny@inspirum.cz instead of using the issue tracker.


## Credits

- [Tomáš Novotný](https://github.com/tomas-novotny)
- [All Contributors][link-contributors]


## License

The MIT License (MIT). Please see [License File][link-licence] for more information.


[ico-license]:              https://img.shields.io/github/license/inspirum/balikobot-php-laravel.svg?style=flat-square&colorB=blue
[ico-workflow]:             https://img.shields.io/github/actions/workflow/status/inspirum/balikobot-php-laravel/master.yml?branch=master&style=flat-square
[ico-packagist-stable]:     https://img.shields.io/packagist/v/inspirum/balikobot-laravel.svg?style=flat-square&colorB=blue
[ico-packagist-download]:   https://img.shields.io/packagist/dt/inspirum/balikobot-laravel.svg?style=flat-square&colorB=blue
[ico-phpstan]:              https://img.shields.io/badge/style-level%209-brightgreen.svg?style=flat-square&label=phpstan

[link-balikobot]:           https://github.com/inspirum/balikobot-php
[link-author]:              https://github.com/inspirum
[link-contributors]:        https://github.com/inspirum/balikobot-php-laravel/contributors
[link-licence]:             ./LICENSE.md
[link-changelog]:           ./CHANGELOG.md
[link-contributing]:        ./docs/CONTRIBUTING.md
[link-code-of-conduct]:     ./docs/CODE_OF_CONDUCT.md
[link-workflow]:            https://github.com/inspirum/balikobot-php-laravel/actions
[link-packagist-stable]:    https://packagist.org/packages/inspirum/balikobot-laravel
[link-packagist-download]:  https://packagist.org/packages/inspirum/balikobot-laravel/stats
[link-phpstan]:             https://github.com/phpstan/phpstan
