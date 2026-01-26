<?php

namespace Dniccum\Vaultr;

use Dniccum\Vaultr\Commands\VaultrEnvironmentsCommand;
use Dniccum\Vaultr\Commands\VaultrInstallCommand;
use Dniccum\Vaultr\Commands\VaultrKeysCommand;
use Dniccum\Vaultr\Commands\VaultrShareCommand;
use Dniccum\Vaultr\Commands\VaultrVariablesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VaultrServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('vaultr')
            ->hasConfigFile('vaultr')
            ->hasCommands([
                VaultrEnvironmentsCommand::class,
                VaultrInstallCommand::class,
                VaultrKeysCommand::class,
                VaultrVariablesCommand::class,
                VaultrShareCommand::class,
            ]);
    }
}
