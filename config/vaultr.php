<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vaultr API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for your Vaultr API instance. This should include the
    | protocol (http/https) and domain, but not the /api path.
    |
    */
    'api_url' => env('VAULTR_API_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Vaultr API Token
    |--------------------------------------------------------------------------
    |
    | Your personal API token for authenticating with the Vaultr API.
    | You can generate this token using the vaultr:token command or
    | through the Vaultr web interface.
    |
    */
    'api_token' => env('VAULTR_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Vaultr Application ID
    |--------------------------------------------------------------------------
    |
    | The unique application ID supplied to you by the Vaultr application.
    |
    */
    'application_id' => env('VAULTR_APPLICATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Default Organization ID
    |--------------------------------------------------------------------------
    |
    | The default organization ID to use when not specified in commands.
    | This can be overridden using the --organization option.
    |
    */
    'default_organization_id' => env('VAULTR_DEFAULT_ORGANIZATION_ID'),
    /*
    |--------------------------------------------------------------------------
    | Ignored Environment Variables
    |--------------------------------------------------------------------------
    |
    | A list of environment variables that should be ignored when pushing
    | to or pulling from the Vaultr API. These keys are case-sensitive.
    |
    | The VAULTR_ prefix is always ignored by default.
    |
    */
    'ignored_variables' => [],
];
