<?php

namespace Dniccum\Vaultr;

use Dniccum\VaultrCli\Commands\VaultrApplicationsCommand;
use Dniccum\VaultrCli\Commands\VaultrEnvironmentsCommand;
use Dniccum\VaultrCli\Commands\VaultrKeysCommand;
use Dniccum\VaultrCli\Commands\VaultrOrganizationsCommand;
use Dniccum\VaultrCli\Commands\VaultrTokenCommand;
use Dniccum\VaultrCli\Commands\VaultrVariablesCommand;
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
                VaultrApplicationsCommand::class,
                VaultrEnvironmentsCommand::class,
                VaultrKeysCommand::class,
                VaultrOrganizationsCommand::class,
                VaultrTokenCommand::class,
                VaultrVariablesCommand::class,
            ]);
    }
}
