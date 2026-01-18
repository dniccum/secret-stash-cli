{{-- resources/boost/guidelines/core.blade.php --}}

# Vaultr CLI Guidelines

This package allows users to interact with the Vaultr cloud application within the context of their own Laravel/PHP application.

## Foundational Context
- **PHP Version**: 8.4.14
- **Laravel Versions**: ^11.0 or ^12.0
- **Testing**: Pest v4 with PHPUnit 12 and Orchestra Testbench.
- **Code Style**: Laravel Pint.

## Development Conventions
- **Contract First**: Although not currently widespread in the codebase, new drivers or services should define a PHP interface in `src/Contracts` first.
- **Service Container**: Bind all Vaultr services in the `VaultrServiceProvider`. Use dependency injection for classes like `VaultrClient`.
- **Zero-Dependency Core**: Keep core secret-fetching logic free of Laravel-specific helpers where possible.
- **Security**: Never log raw secret values. Use `[REDACTED]` or mask values. Ensure secrets are cleared from memory once used.
- **HTTP Client**: Use Laravel's `Http` client facade with a defined `User-Agent`.

## Artisan Commands
- **Namespace**: All commands must be prefixed with `vaultr:`.
- **Current Commands**:
    - `vaultr:environments`: Manage environments.
    - `vaultr:keys`: Manage API keys.
    - `vaultr:variables`: Manage application secrets/variables.
- **UX**: Use `laravel/prompts` for all interactive CLI interactions.

## Configuration
- **Environment Variables**: Use `VAULTR_` prefix (e.g., `VAULTR_API_KEY`, `VAULTR_API_URL`).
- **Config File**: `config/vaultr.php`. Users publish it via `php artisan vendor:publish --tag=vaultr-config`.

## Testing Standards
- **Mocking**: Use `Http::fake()` or mock the `VaultrClient` class in Feature tests.
- **Pest**: All tests must use Pest syntax.
- **Assertions**: Use specific status code assertions (e.g., `assertSuccessful`, `assertForbidden`).

## Project Structure
- **Source Code**: `src/` (Namespace: `Dniccum\Vaultr\`)
- **Commands**: `src/Commands/`
- **Enums**: `src/Enums/`
- **Exceptions**: `src/Exceptions/`
- **Tests**: `tests/Feature/` and `tests/Unit/`
- **Configuration**: `config/vaultr.php`

These guidelines ensure that AI-generated code remains consistent with the `vaultr-cli` architecture and best practices.
