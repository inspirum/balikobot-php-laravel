<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->addPathsToScan([
        __DIR__ . '/src',
    ], false)
    ->addPathsToScan([
        __DIR__ . '/tests',
    ], true)
    ->setFileExtensions(['php'])
    ->disableComposerAutoloadPathScan()
    ->ignoreErrorsOnPackages([
        'illuminate/contracts',
        'illuminate/support',
    ], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackages([
        'laravel/framework',
    ], [ErrorType::SHADOW_DEPENDENCY]);
