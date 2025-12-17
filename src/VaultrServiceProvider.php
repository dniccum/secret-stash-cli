<?php

namespace Dniccum\Vaultr;

// use Dniccum\Vaultr\Commands\VaultrApplicationsCommand;
use Dniccum\Vaultr\Commands\VaultrEnvironmentsCommand;
// use Dniccum\Vaultr\Commands\VaultrKeysCommand;
// use Dniccum\Vaultr\Commands\VaultrOrganizationsCommand;
// use Dniccum\Vaultr\Commands\VaultrTokenCommand;
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
                //                VaultrApplicationsCommand::class,
                VaultrEnvironmentsCommand::class,
                //                VaultrKeysCommand::class,
                //                VaultrOrganizationsCommand::class,
                //                VaultrTokenCommand::class,
                VaultrVariablesCommand::class,
            ]);
    }
}
