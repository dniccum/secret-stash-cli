# SecretStash CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dniccum/secret-stash-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/secret-stash-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/secret-stash-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dniccum/secret-stash-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/secret-stash-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dniccum/secret-stash-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dniccum/secret-stash-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/secret-stash-cli)

A beautiful Laravel Composer package that provides Artisan commands for interacting with the SecretStash REST API. Manage your organizations, applications, environments, and variables directly from the command line with an intuitive, interactive interface.

## Table of Contents

- [**Features**](#features)
- [**Requirements**](#requirements)
- [**Installation**](#installation)
    - [**API Key**](#api-key)
    - [**Application Id**](#application-id)
- [**Configuration**](#configuration)
- [**Usage**](#usage)
    - [**Managing Variables**](#managing-variables)
        - [Pulling Variables](#pulling-variables) (`secret-stash:variables pull`)
        - [Pushing Variables](#pushing-variables) (`secret-stash:variables push`)
        - [Listing Variables](#listing-variables) (`secret-stash:variables list`)
    - [**Managing Environments**](#managing-environments)
        - [List Environments](#list-environments) (`secret-stash:environments list`)
        - [Create Environment](#create-environment) (`secret-stash:environments create`)
    - [**Managing Encryption Keys**](#managing-encryption-keys)
        - [Generate a Key](#generate-a-key) (`secret-stash:keys generate`)
        - [Set an Existing Key](#set-an-existing-key) (`secret-stash:keys set`)
        - [List Keys](#list-keys) (`secret-stash:keys list`)
- [**Changelog**](#changelog)
- [**Contributing**](#contributing)
- [**Credits**](#credits)
- [**License**](#license)

## Features

- 🎨 **Beautiful Console Interface** - Built with Laravel Prompts for an interactive, user-friendly experience
- 🔐 **Secure API Authentication** - Uses Laravel Sanctum tokens for secure API access
- 🚀 **Easy Installation** - Simple Composer installation with Laravel auto-discovery
- 💾 **Environment File Sync** - Pull and push variables to/from .env files
- ✨ **Interactive Prompts** - Smart prompts guide you through each operation

## Requirements

- PHP 8.4 or higher
- Laravel 11 or higher
- A SecretStash API Key

## Installation

You can install the package via Composer:

```bash
composer require dniccum/secret-stash-cli
```

Execute the installation command. This will optionally publish the configuration file and create an environment key used to encrypt your variables before they are sent to SecretStash's servers:

```bash
php artisan secret-stash:install
```

> [!IMPORTANT]
> This package creates a `~/.secret-stash/keys.json` file on your machine. Ensure this directory is secure as it contains the keys required to decrypt your environment variables.

### API Key

Within the SecretStash application interface, go to your user's profile settings, and access the "Tokens" tab. Provide a unique name for a token and then click "Create." Copy the provided token and set it as the `SECRET_STASH_API_KEY` environment variable in your `.env` file:

```dotenv
SECRET_STASH_API_KEY=your_token_here
```

### Application ID

If you have not already, create a new application within SecretStash. You can do this by navigating to the "Dashboard" page in the SecretStash application interface and click "New application." Provide a name for your application and click "Create." Copy the provided application ID and set it as the `SECRET_STASH_APPLICATION_ID` environment variable in your `.env` file:

```dotenv
SECRET_STASH_APPLICATION_ID=your_application_id_here
```

> [!NOTE]
> The SecretStash CLI requires both the API key and Application ID to be present to work. Failure to set them will throw an error.

## Configuration

If you have not already, publish the configuration file using `php artisan vendor:publish php artisan vendor:publish --tag=secret-stash-config`. This will create a `config/secret-stash.php` file where you can customize the package's behavior.

### Ignored Variables

In the event that you do want SecretStash to make a record of variables, you can define them within the `secret-stash.ignored_variables` section of the `config/secret-stash.php` file. This is useful for variables that are dynamically generated, specific to your environment, or should not be stored in SecretStash.

**Example**

The example below would not sync (pull or push) the `DB_DATABASE` or `DB_USERNAME` variables to the target `.env` file.

```php
return [
    'ignored_variables' => [
        'DB_DATABASE',
        'DB_USERNAME',
        // Add more variables as needed
    ],
];
```

> [!NOTE]
> The `secret-stash:variables push` command automatically ignores variables starting with `SECRET_STASH_` to prevent circular configuration issues.

## Usage

SecretStash CLI provides a set of Artisan commands to interact with your SecretStash application. Most commands are interactive, but they also support options for CI/CD environments.

### Managing Variables

The primary purpose of this package is to sync your local `.env` file with the SecretStash API.

#### Pulling Variables

The `pull` command retrieves variables from SecretStash, decrypts them using your local environment key, and updates your local `.env` file.

```shell script
php artisan secret-stash:variables pull
```

**Options:**
- `--environment`: Specify the environment slug (e.g., `production`). Defaults to the environment that is set in your `APP_ENV` definition.
- `--file`: The path to the file you want to update (defaults to `.env`).
- `--key`: Provide a specific encryption key if it's not in your local `keys.json`. *Note: assuming you followed the installation step above, you should not have to use this.*

#### Pushing Variables

The `push` command reads your local `.env` file, encrypts the values, and sends them to the SecretStash API.

```shell script
php artisan secret-stash:variables push
```

*Note: By default, any variable starting with `SECRET_STASH_` or defined in the `ignored_variables` config array will be skipped to prevent circular dependencies.*

**Options:**

- `--environment`: Specify the destination environment. If the environment doesn't exist, you will be prompted to create it.
- `--file`: The source file to read (defaults to `.env`).

#### Listing Variables

To see a summary of the variables currently stored in SecretStash for your environment:

```shell script
php artisan secret-stash:variables list
```

---

### Managing Environments

Environments allow you to group variables by stage (e.g., staging, production).

#### List Environments

View all environments associated with your application:

```shell script
php artisan secret-stash:environments list
# or using the alias
php artisan secret-stash:env
```

#### Create Environment

Create a new environment container:

```shell script
php artisan secret-stash:environments create --name="Staging" --slug="staging" --type="development"
```

---

### Managing Encryption Keys

SecretStash CLI uses client-side encryption. This means your raw values never touch SecretStash's servers; only the encrypted payloads do. Keys are stored locally in `~/.secret-stash/keys.json`.

> [!IMPORTANT]
> This portion of the package is something that you will likely not need. Please be sure to perform the installation command `php artisan secret-stash:install`. This will generate the necessary encryption keys for you.

#### Generate a Key

Generate a new 32-byte encryption key for an environment:

```shell script
php artisan secret-stash:keys generate --environment=production
```

#### Set an Existing Key

If you are setting up a new machine and already have a key:

```shell script
php artisan secret-stash:keys set --environment=production --key=your-base64-encoded-key
```

#### List Keys

View which environments have keys configured on your local machine:

```shell script
php artisan secret-stash:keys list
```

## Testing

Use Composer: 

```bash
composer test
```

or Pest:

```bash
./vendor/bin/pest
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

[//]: # (## Security Vulnerabilities)

[//]: # ()
[//]: # (Please review [our security policy]&#40;../../security/policy&#41; on how to report security vulnerabilities.)

## Credits

- [Doug Niccum](https://github.com/dniccum)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
