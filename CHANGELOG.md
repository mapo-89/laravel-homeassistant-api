# Changelog

All notable changes to `laravel-homeassistant-api` will be documented in this file

## 1.0.0 - 2025-12-07

### Added
- First stable version of the Laravel Home Assistant API package
- API client based on Guzzle for communication with the Home Assistant REST API
- `HomeAssistantAPI` for states and entities
- Service provider including config publishing
- Artisan command ha:call for fetching HA entities
- Improved error handling with response inspection
- Automatic URL normalization for base URL
