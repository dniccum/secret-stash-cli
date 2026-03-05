<?php

namespace Dniccum\SecretStash\Commands\Traits;

use Dniccum\SecretStash\Exceptions\Keys\NoApplicationsAvailable;
use Dniccum\SecretStash\SecretStashClient;

use function Laravel\Prompts\select;

trait UsesApplicationId
{
    /**
     * Retrieve the application ID associated with the given organization.
     *
     * If an application ID is not provided via an option, this method fetches
     * the list of applications from the specified organization and prompts
     * the user to select one.
     *
     * @param  SecretStashClient  $client  The client responsible for communicating with the application API.
     * @param  string  $organizationId  The identifier of the organization whose applications are being retrieved.
     * @return string The selected or provided application ID.
     *
     * @throws NoApplicationsAvailable If no applications exist for the given organization.
     */
    protected function getApplicationId(SecretStashClient $client, string $organizationId): string
    {
        $applicationId = $this->option('application');

        if (! $applicationId) {
            $response = $client->getApplications();
            $applications = $response['data'] ?? [];

            if (empty($applications)) {
                throw new NoApplicationsAvailable;
            }

            $choices = [];
            foreach ($applications as $app) {
                $choices[$app['id']] = $app['name'];
            }

            $applicationId = select(
                label: 'Select an application',
                options: $choices
            );
        }

        return $applicationId;
    }
}
