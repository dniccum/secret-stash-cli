# Changelog

All notable changes to `secret-stash-cli` will be documented in this file.

## v1.0.0 - 2026-03-28

### What's Changed

* feat: add Laravel 13 (illuminate ^13.0) support [SEC-16] by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/39
* feat: Add non-Laravel framework support (SEC2-6) by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/40

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.8.1...v1.0.0

## v0.8.1 - 2026-03-27

### What's Changed

* feat: enhance SecretStashVariablesCommand to prompt user for action s… by @dniccum in https://github.com/dniccum/secret-stash-cli/pull/38

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.8.0...v0.8.1

## v0.8.0 - 2026-03-26

### What's Changed

* Fix: Environments command with no action prompts user instead of defaulting to list by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/32
* fix: handle empty variable values during CLI push by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/34
* feat: add temporary device key support for CI/CD pipelines (SEC-12) by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/36

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.7.0...v0.8.0

## v0.7.0 - 2026-03-25

### What's Changed

* fix: validate environment existence before fetching envelope key by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/26
* fix: remove redundant null check on aesGcmEncrypt return value by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/29

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.6.3...v0.7.0

## v0.6.3 - 2026-03-23

### What's Changed

* Handle null payloads gracefully and catch Throwable (SEC2-4) by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/25

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.6.2...v0.6.3

## v0.6.2 - 2026-03-23

### What's Changed

* fix: differentiate 401 (invalid token) from 403 (authorization failure) in error handling by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/23

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.6.1...v0.6.2

## v0.6.1 - 2026-03-23

### What's Changed

* fix: replace array access with property access on RSAKeyPair objects by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/21

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.6.0...v0.6.1

## v0.6.0 - 2026-03-23

### What's Changed

* fix: clean up CLI error messages to hide raw API details (SEC-6) by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/19

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.5.0...v0.6.0

## v0.5.0 - 2026-03-17

### What's Changed

* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/dniccum/secret-stash-cli/pull/11
* feat: add Testing environment type with CLI push restrictions by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/12
* docs: simplify README and add open graph image by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/13

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.4.0...v0.5.0

## v0.4.0 - 2026-03-16

### What's Changed

* Feature/default ignored variables by @dniccum in https://github.com/dniccum/secret-stash-cli/pull/9
* fix: resolve PHPStan unmatched ignore pattern and Windows test path issues by @devin-ai-integration[bot] in https://github.com/dniccum/secret-stash-cli/pull/10

### New Contributors

* @devin-ai-integration[bot] made their first contribution in https://github.com/dniccum/secret-stash-cli/pull/10

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.3.0...v0.4.0

## v0.3.0 - 2026-03-05

### What's Changed

* Update laravel/boost requirement from ^1.8 to ^2.0 by @dependabot[bot] in https://github.com/dniccum/secret-stash-cli/pull/5
* Feature/secret stash refactor by @dniccum in https://github.com/dniccum/secret-stash-cli/pull/6
* Feature/secret stash refactor by @dniccum in https://github.com/dniccum/secret-stash-cli/pull/7
* Feature/documentation update by @dniccum in https://github.com/dniccum/secret-stash-cli/pull/8

**Full Changelog**: https://github.com/dniccum/secret-stash-cli/compare/v0.2.0...v0.3.0
