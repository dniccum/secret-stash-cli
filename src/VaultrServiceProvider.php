<?php

namespace Dniccum\Vaultr;

use Dniccum\Vaultr\Commands\VaultrEnvironmentsCommand;
use Dniccum\Vaultr\Commands\VaultrKeysCommand;
use Dniccum\Vaultr\Commands\VaultrVariablesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VaultrServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('vaultr')
            ->hasConfigFile('vaultr')
            ->hasCommands([
                VaultrEnvironmentsCommand::class,
                VaultrKeysCommand::class,
                VaultrVariablesCommand::class,
            ]);
    }
}
