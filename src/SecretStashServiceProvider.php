<?php

namespace Dniccum\SecretStash;

use Dniccum\SecretStash\Commands\SecretStashEnvironmentsCommand;
use Dniccum\SecretStash\Commands\SecretStashInstallCommand;
use Dniccum\SecretStash\Commands\SecretStashKeysCommand;
use Dniccum\SecretStash\Commands\SecretStashShareCommand;
use Dniccum\SecretStash\Commands\SecretStashVariablesCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SecretStashServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('secret-stash')
            ->hasConfigFile('secret-stash')
            ->hasCommands([
                SecretStashEnvironmentsCommand::class,
                SecretStashInstallCommand::class,
                SecretStashKeysCommand::class,
                SecretStashVariablesCommand::class,
                SecretStashShareCommand::class,
            ]);
    }
}
