<vaultr-package-guidelines>
# Vaultr CLI Guidelines

=== foundation rules ===

## Foundational Context

This is a package that allows users to interact with the Vaultr cloud application within the context of their own Laravel/PHP application. This package provides a series of command line interface commands to push, pull, and view any application-secrets they have stored in their organization's Vaultr account.

- php - 8.4.14
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12

## Conventions
- **Contract First**: Always define a PHP interface in `src/Contracts` before implementing a new driver or service.
- **Service Container**: Bind all Vaultr services in the `VaultrServiceProvider`. Avoid using `new` for classes that require configuration.
- **Zero-Dependency Core**: Keep the core secret-fetching logic free of Laravel-specific helpers where possible, allowing for easier unit testing.

## Security & Secrets
- **No Logging**: Never log raw secret values. Use `[REDACTED]` or mask values if logging is absolutely necessary for debugging.
- **Memory Safety**: Ensure secrets are cleared from local class variables once they have been injected into the environment.
- **HttpClient**: Use Laravel's `Http` client facade with a defined `User-Agent` identifying the package version.

## Testing Standards
- **Mocking Vaultr API**: Use `Http::fake()` in Feature tests to simulate Vaultr API responses. Never make real API calls in the test suite.
- **Contract Tests**: Ensure every implementation of `SecretProvider` passes the same set of Pest expectation tests.
- **Workbench**: Use `orchestra/testbench` to verify that the package integrates correctly with the Laravel container.

## Artisan Commands
- **Namespacing**: All package commands must be prefixed with `vaultr:`, for example: `vaultr:fetch` or `vaultr:status`.
- **Output**: Use `laravel/prompts` for interactive commands to maintain a modern CLI experience.

## Configuration
- **Environment Variables**: Use `VAULTR_` prefix for all package-specific environment variables (e.g., `VAULTR_API_KEY`).
- **Config File**: The default config should be published via `php artisan vendor:publish --tag=vaultr-config`.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Unit and Feature tests must be grouped by subject or feature to keep the test suite organized. If a feature does not have an existing test suite, you may create a new feature file.
- Pest tests look and behave like this:
  <code-snippet name="Basic Pest Test Example" lang="php">
  it('is true', function () {
  expect(true)->toBeTrue();
  });
  </code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `./vendor/bin/pest`.
- To run all tests in a file: `./vendor/bin/pest tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `./vendor/bin/pest --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
  <code-snippet name="Pest Example Asserting postJson Response" lang="php">
  it('returns all', function () {
  $response = $this->postJson('/api/docs', []);

  $response->assertSuccessful();
  });
  </code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!');

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `./vendor/bin/pest` with a specific filename or filter.
</vaultr-package-guidelines>
