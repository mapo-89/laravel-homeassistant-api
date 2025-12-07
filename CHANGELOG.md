# Changelog

All notable changes to `laravel-homeassistant-api` will be documented in this file

# Changelog

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
