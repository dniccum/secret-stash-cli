# Contributing

Thank you for considering contributing to the SecretStash CLI! To maintain a high standard for our code and ensure a smooth process, please follow these guidelines.

## Code of Conduct

Help us keep the SecretStash community open and inclusive. Please be kind and respectful in all interactions.

## Bug Reports

If you discover a bug, please [open an issue](https://github.com/dniccum/secret-stash-cli/issues) on GitHub. To help us fix it quickly, please include:
- A clear, descriptive title.
- Steps to reproduce the issue.
- Your environment details (PHP version, Laravel version, OS).
- Expected vs. actual behavior.

## Pull Requests

1. **Fork the repository** and create your branch from `main`.
2. **Install dependencies**:
   ```bash
   composer install
   ```
3. **Coding Style**: We follow the PSR-12 coding standard. You can automatically fix code style issues by running:
   ```bash
   composer fix-style
   ```
4. **Tests**: Ensure that your PR includes tests for any new functionality or bug fixes. We use [Pest](https://pestphp.com/).
   ```bash
   composer test
   ```
5. **Documentation**: If you're adding a new feature or changing an existing one, please update the `README.md` accordingly.
6. **One PR per feature**: Keep your PRs focused. If you have multiple unrelated changes, please submit them as separate pull requests.
7. **Commit messages**: Use descriptive commit messages (e.g., `feat: add support for custom config paths` instead of `update code`).

## Security Vulnerabilities

If you discover a security-related issue, please email [your-email@example.com] instead of using the public issue tracker. We take security seriously and will respond promptly.

## Development Workflow

This package leverages several modern PHP and Laravel features:
- **PHP 8.4+**: Utilize latest language features where appropriate (property hooks, etc.).
- **Laravel Prompts**: All CLI interactions should use Laravel Prompts for a consistent, beautiful UI.
- **Client-Side Encryption**: Ensure any changes to variable handling maintain the "encrypted before sending" architecture.

## Thank You!

Your contributions help make SecretStash CLI a better tool for the entire Laravel community. We appreciate your time and effort!

---

*Inspired by the [Spatie Contribution Guidelines](https://github.com/spatie/laravel-permission/blob/master/CONTRIBUTING.md).*
