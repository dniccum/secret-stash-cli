# Vaultr CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dniccum/vaultr-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/vaultr-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/vaultr-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dniccum/vaultr-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/vaultr-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dniccum/vaultr-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dniccum/vaultr-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/vaultr-cli)

A beautiful Laravel Composer package that provides Artisan commands for interacting with the Vaultr REST API. Manage your organizations, applications, environments, and variables directly from the command line with an intuitive, interactive interface.

## Table of Contents

Here is the Table of Contents for the Vaultr CLI documentation:

## Table of Contents

- [**Features**](#features)
- [**Requirements**](#requirements)
- [**Installation**](#installation)
- [**Usage**](#usage)
    - [**Managing Variables**](#managing-variables)
        - [Pulling Variables](#pulling-variables) (`vaultr:variables pull`)
        - [Pushing Variables](#pushing-variables) (`vaultr:variables push`)
        - [Listing Variables](#listing-variables) (`vaultr:variables list`)
    - [**Managing Environments**](#managing-environments)
        - [List Environments](#list-environments) (`vaultr:environments list`)
        - [Create Environment](#create-environment) (`vaultr:environments create`)
    - [**Managing Encryption Keys**](#managing-encryption-keys)
        - [Generate a Key](#generate-a-key) (`vaultr:keys generate`)
        - [Set an Existing Key](#set-an-existing-key) (`vaultr:keys set`)
        - [List Keys](#list-keys) (`vaultr:keys list`)
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
- A Vaultr API Key

## Installation

You can install the package via Composer:

```bash
composer require dniccum/vaultr-cli
```

Execute the installation command. This will optionally publish the configuration file and create an environment key used to encrypt your variables before they are sent to Vaultr's servers:

```bash
php artisan vaultr:install
```

> [!IMPORTANT]
> This package creates a `~/.vaultr/keys.json` file on your machine. Ensure this directory is secure as it contains the keys required to decrypt your environment variables.

## Usage

Vaultr CLI provides a set of Artisan commands to interact with your Vaultr application. Most commands are interactive, but they also support options for CI/CD environments.

### Managing Variables

The primary purpose of this package is to sync your local `.env` file with the Vaultr API.

#### Pulling Variables

The `pull` command retrieves variables from Vaultr, decrypts them using your local environment key, and updates your local `.env` file.

```shell script
php artisan vaultr:variables pull
```

**Options:**
- `--environment`: Specify the environment slug (e.g., `production`). Defaults to the environment that is set in your `APP_ENV` definition.
- `--file`: The path to the file you want to update (defaults to `.env`).
- `--key`: Provide a specific encryption key if it's not in your local `keys.json`. *Note: assuming you followed the installation step above, you should not have to use this.*

#### Pushing Variables

The `push` command reads your local `.env` file, encrypts the values, and sends them to the Vaultr API.

```shell script
php artisan vaultr:variables push
```

*Note: By default, any variable starting with `VAULTR_` or defined in the `ignored_variables` config array will be skipped to prevent circular dependencies.*

**Options:**

- `--environment`: Specify the destination environment. If the environment doesn't exist, you will be prompted to create it.
- `--file`: The source file to read (defaults to `.env`).

#### Listing Variables

To see a summary of the variables currently stored in Vaultr for your environment:

```shell script
php artisan vaultr:variables list
```

---

### Managing Environments

Environments allow you to group variables by stage (e.g., staging, production).

#### List Environments

View all environments associated with your application:

```shell script
php artisan vaultr:environments list
# or using the alias
php artisan vaultr:env
```

#### Create Environment

Create a new environment container:

```shell script
php artisan vaultr:environments create --name="Staging" --slug="staging" --type="development"
```

---

### Managing Encryption Keys

Vaultr CLI uses client-side encryption. This means your raw values never touch Vaultr's servers; only the encrypted payloads do. Keys are stored locally in `~/.vaultr/keys.json`.

> [!IMPORTANT]
> This portion of the package is something that you will likely not need. Please be sure to perform the installation command `php artisan vaultr:install`. This will generate the necessary encryption keys for you.

#### Generate a Key

Generate a new 32-byte encryption key for an environment:

```shell script
php artisan vaultr:keys generate --environment=production
```

#### Set an Existing Key

If you are setting up a new machine and already have a key:

```shell script
php artisan vaultr:keys set --environment=production --key=your-base64-encoded-key
```

#### List Keys

View which environments have keys configured on your local machine:

```shell script
php artisan vaultr:keys list
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
