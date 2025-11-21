# Vaultr CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dniccum/vaultr-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/vaultr-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/vaultr-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dniccum/vaultr-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/vaultr-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dniccum/vaultr-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dniccum/vaultr-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/vaultr-cli)

A beautiful Laravel Composer package that provides Artisan commands for interacting with the Vaultr REST API. Manage your organizations, applications, environments, and variables directly from the command line with an intuitive, interactive interface.

## Features

- 🎨 **Beautiful Console Interface** - Built with Laravel Prompts for an interactive, user-friendly experience
- 🔐 **Secure API Authentication** - Uses Laravel Sanctum tokens for secure API access
- 📦 **Complete Resource Management** - Manage organizations, applications, environments, and variables
- 🚀 **Easy Installation** - Simple Composer installation with Laravel auto-discovery
- 💾 **Environment File Sync** - Pull and push variables to/from .env files
- ✨ **Interactive Prompts** - Smart prompts guide you through each operation

## Requirements

- PHP 8.4 or higher
- Laravel 11.0 or 12.0
- A Vaultr API Key

## Installation

You can install the package via composer:

```bash
composer require dniccum/vaultr-cli
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag=vaultr-config
```

This is the contents of the published config file:

```php
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
    | Default Organization ID
    |--------------------------------------------------------------------------
    |
    | The default organization ID to use when not specified in commands.
    | This can be overridden using the --organization option.
    |
    */
    'default_organization_id' => env('VAULTR_DEFAULT_ORGANIZATION_ID'),
];
```

## Usage

```php
$vaultrCli = new Doug Niccum Design, LLC\VaultrCli();
echo $vaultrCli->echoPhrase('Hello, Doug Niccum Design, LLC!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Doug Niccum](https://github.com/dniccum)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
