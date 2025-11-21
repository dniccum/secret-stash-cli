<?php

namespace Dniccum\Vaultr;

use Dniccum\Vaultr\Commands\VaultrCommand;
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
            ->name('vaultr-cli')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_vaultr_cli_table')
            ->hasCommand(VaultrCommand::class);
    }
}
