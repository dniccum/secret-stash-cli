[![Latest Version on Packagist](https://img.shields.io/packagist/v/dniccum/secret-stash-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/secret-stash-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/secret-stash-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dniccum/secret-stash-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/secret-stash-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dniccum/secret-stash-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dniccum/secret-stash-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/secret-stash-cli)

![SecretStash](og-image.png)

# SecretStash CLI

A Laravel Composer package that provides Artisan commands for interacting with the [SecretStash](https://secretstash.cloud) REST API. Manage your environment variables directly from the command line with an intuitive, interactive interface.

## Requirements

- PHP 8.2 or higher
- Laravel 11 or higher
- A SecretStash API Key

## Installation

Install the package via Composer:

```bash
composer require dniccum/secret-stash-cli
```

Run the installer to publish the configuration file and generate the encryption keys used to secure your variables:

```bash
php artisan secret-stash:install
```

> [!IMPORTANT]
> This package creates a `~/.secret-stash` directory on your machine. Ensure this folder is secure as it contains the keys required to decrypt your environment variables.

## Configuration

Add the following environment variables to your application's `.env` file:

```dotenv
SECRET_STASH_API_TOKEN=your_token_here
SECRET_STASH_APPLICATION_ID=your_application_id_here
```

- **API Key**: Create a token in SecretStash by navigating to your profile settings and accessing the "Tokens" tab.
- **Application ID**: Create or select an application in SecretStash and copy its ID from the dashboard.

> [!NOTE]
> Both the API key and Application ID are required. The CLI will throw an error if either is missing.

## Quick Example

Pull your environment's variables from SecretStash into your local `.env` file:

```bash
php artisan secret-stash:variables pull
```

Push your local `.env` variables to SecretStash:

```bash
php artisan secret-stash:variables push
```

For the full list of available commands and options, visit the [SecretStash CLI documentation](https://docs.secretstash.cloud/command-line-interface/commands).

## Testing

```bash
composer test
```

or:

```bash
./vendor/bin/pest
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Doug Niccum](https://github.com/dniccum)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
