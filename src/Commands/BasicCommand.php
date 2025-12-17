<?php

namespace Dniccum\Vaultr\Commands;

use Dniccum\Vaultr\Exceptions\InvalidEnvironmentConfiguration;
use Illuminate\Console\Command;
use function Laravel\Prompts\text;

abstract class BasicCommand extends Command
{
    protected string $applicationId;

    protected string $environmentSlug;

    /**
     * @throws \Throwable
     */
    protected function setEnvironment(): void
    {
        $this->applicationId = $this->hasOption('application') && $this->option('application') ? $this->option('application') : config('vaultr.default_application_id', '');
        $this->environmentSlug = $this->hasOption('environment') && $this->option('environment') ? $this->option('environment') : config('app.env');

        throw_if(empty($this->applicationId), new InvalidEnvironmentConfiguration('An application ID must be provided.'));
        while (empty($this->environmentSlug)) {
            $this->environmentSlug = text('The environment that you would like to interact with', required: true);
        }
    }
}
