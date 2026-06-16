[![Latest Version on Packagist](https://img.shields.io/packagist/v/dniccum/secret-stash-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/secret-stash-cli)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/secret-stash-cli/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/dniccum/secret-stash-cli/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/dniccum/secret-stash-cli/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/dniccum/secret-stash-cli/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/dniccum/secret-stash-cli.svg?style=flat-square)](https://packagist.org/packages/dniccum/secret-stash-cli)

![SecretStash](og-image.png)

# SecretStash CLI (PHP / Laravel)

> Stop sharing `.env` files in Slack.

The SecretStash CLI lets you securely sync environment variables across your team, applications, and environments—directly from your Laravel or PHP project.

Built for developers who want a simple, secure way to manage secrets without breaking their workflow.

## Requirements

- PHP 8.2 or higher
- Laravel 11 or higher
- A SecretStash API Key

---

## 🚀 Why SecretStash?

If you’ve ever:

- Shared `.env` files over Slack, email, or Notion
- Accidentally committed secrets to Git
- Struggled keeping dev/staging/prod configs in sync
- Spent too long onboarding teammates with environment setup

👉 SecretStash fixes this in minutes.

---

## ⚡ Quick Start (2 Minutes)

### 1. Install

```bash
composer require dniccum/secret-stash-cli
```

---

### 2. Authenticate

```bash
php artisan secret-stash:login
```

---

### 3. Pull your environment variables

```bash
php artisan secret-stash:pull
```

✅ Your `.env` file is now synced and secure.

---

## 🌐 Use with SecretStash Cloud

The CLI is designed to work with SecretStash Cloud:

👉 https://secretstash.cloud

With the cloud platform, you can:

- Manage applications and environments in one place
- Share secrets securely across your team
- Sync configs across multiple machines instantly
- Avoid ever exposing sensitive values

Start free — no credit card required.

---

## 🔐 Secure by Design

SecretStash uses **zero-knowledge encryption**:

- Secrets are encrypted locally before being sent
- Your encryption keys never leave your machine
- SecretStash servers never see your raw values

> Only you and your team can decrypt your secrets.

---

## ⚙️ How It Works

1. Store your environment variables in SecretStash Cloud
2. Encrypt values locally before transmission
3. Use the CLI to sync `.env` files across environments

This ensures a secure, consistent workflow from local development to production.

---

## 💡 Common Use Cases

### 👥 Team Collaboration
Keep your entire team in sync without sharing sensitive files manually.

### 🌍 Environment Management
Manage separate configs for local, staging, and production.

### 🚀 Laravel Development
Plug directly into your Laravel workflow via Artisan commands.

### 🔁 CI/CD Pipelines
Securely pull environment variables during deployment.

---

## 📦 Available Commands

```bash
php artisan secret-stash:login   # Authenticate with SecretStash
php artisan secret-stash:pull    # Pull environment variables
php artisan secret-stash:push    # Push local changes
```

---

## 🧪 Import Existing Projects

Already have a `.env` file?

You can import your existing variables into SecretStash and start syncing immediately.

---

## 🆚 Why Not Just Use `.env` Files?

`.env` files alone:

- ❌ Hard to share securely
- ❌ Easy to leak
- ❌ Not synced across teams
- ❌ No access control

SecretStash:

- ✅ Secure sharing
- ✅ Encrypted end-to-end
- ✅ Built for teams
- ✅ CLI-native workflow

---

## 📚 Documentation

Full documentation available at:

👉 https://docs.secretstash.cloud

---

## ❤️ Ready to stop leaking secrets?

Start using SecretStash today:

👉 https://secretstash.cloud

---

## Testing

```bash
composer test
```

or:

```bash
./vendor/bin/pest
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Doug Niccum](https://github.com/dniccum)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
