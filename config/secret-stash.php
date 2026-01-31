<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SecretStash API Token
    |--------------------------------------------------------------------------
    |
    | Your personal API token for authenticating with the SecretStash API.
    | You can generate this token using the secret-stash:token command or
    | through the SecretStash web interface.
    |
    */
    'api_token' => env('SECRET_STASH_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | SecretStash Application ID
    |--------------------------------------------------------------------------
    |
    | The unique application ID supplied to you by the SecretStash application.
    |
    */
    'application_id' => env('SECRET_STASH_APPLICATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Ignored Environment Variables
    |--------------------------------------------------------------------------
    |
    | A list of environment variables that should be ignored when pushing
    | to or pulling from the SecretStash API. These keys are case-sensitive.
    |
    | The SECRET_STASH_ prefix is always ignored by default.
    |
    */
    'ignored_variables' => [],

    /*
    |--------------------------------------------------------------------------
    | SecretStash API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for your SecretStash API instance. This should include the
    | protocol (http/https) and domain, but not the /api path.
    |
    */
    'api_url' => env('SECRET_STASH_API_URL', 'http://localhost:8000'),
];
