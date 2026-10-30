# Changelog

All notable changes to `laravel-homeassistant-api` will be documented in this file

## v2.0.0 - 2026-10-30

### 💥 Breaking Changes
- Require Laravel 12 or 13
- Separate API endpoints and harden state caching
- Require PHP 8.3 or newer

### ✨ Features
- Configure Home Assistant external URLs over WebSocket
- Read Home Assistant restart requirements
- Read bounded update entity registry metadata
- Manage long-lived Home Assistant access tokens

### 🐛 Fixes
- Isolate instance state queries and sanitize HTTP failures
- Correct REST API behavior and preserve configuration options
- Validate REST responses and support immutable history dates
- Validate API records and normalize connection settings

### ♻️ Refactoring
- Adopt Spatie Laravel Package Tools
- Centralize WebSocket connections and command handling

### 📝 Documentation
- Update package documentation
- Explain service responses and version 2 API changes
- Simplify package documentation

### 🔧 Chores
- Standardize release workflows and validation
- Add Dependabot and document maintenance workflows
- Enforce package code quality checks
- Update development dependencies for larastan and laravel/pint
- Preserve PHP 8.2 tests with updated quality tools

### 📌 Other Changes
- fix for release-script

## [v1.4.2] - 2025-12-15

### Documentation
- : update changelog for v1.4.1


All notable changes to `laravel-homeassistant-api` will be documented in this file

## [v1.4.1] - 2025-12-15

### Documentation
- Update Changelog for v 1.4.0

### Deployment
- update release-script
- update release workflow

## [v1.4.0] - 2025-12-15

### Added
- add proper template rendering with raw response support
- add Config & System API endpoint
- add Logbook API endpoint with DTO support
- add History endpoint support
- add Events endpoint support

### Changed
- (API-Client) improve typing, validation, and error handling
- enhance typing and IDE support in Facade
- improve HomeassistantApiServiceProvider registration and config
- improve HomeassistantApi robustness and DX

### Documentation
- add Events and History API documentation

### Deployment
- update release workflow
- add release workflow
- Change php version in gut hub workflow



## [1.3.0] - 2025-12-14

### Added
- Query builder-style API for states
- Client-side filters: domain, state, entity ID, attributes
- `get()` and `first()` methods

## [1.2.0] - 2025-12-07

### Added
- Introduced **dynamic runtime configuration** for the Home Assistant client.
- `HomeassistantApi::make()` can now accept an array to load configuration dynamically.
- The API client now supports multi-instance usage (e.g. loading URL/token from a database).
- Added validation to ensure dynamic configs provide both `url` and `token`.

### Changed
- States and Services APIs now use the shared injected ApiClient instance.
- Improved documentation with examples for dynamic configuration usage.

### Notes
- Artisan commands still rely on `.env` configuration and cannot use dynamic runtime config.

---

## [1.1.0] - 2025-12-07

### Added

* States API: all(), get(), createOrUpdate(), delete()
* Services API: call(), turnOn(), turnOff(), toggle()
* Typed State DTOs
* New Exceptions: HomeAssistantException, UnauthorizedException, EntityNotFoundException, ServiceCallFailedException
* Improved error handling in API client

### Fixed / Improved

* Clearer structure for States and Services classes
* Laravel-friendly API methods (facades, config, DI)
* Base setup for unit testing

---

## [1.0.0] - 2025-12-07

### Added
- First stable version of the Laravel Home Assistant API package
- API client based on Guzzle for communication with the Home Assistant REST API
- `HomeAssistantAPI` for states and entities
- Service provider including config publishing
- Artisan command ha:call for fetching HA entities
- Improved error handling with response inspection
- Automatic URL normalization for base URL
