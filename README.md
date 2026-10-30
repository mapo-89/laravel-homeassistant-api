# Laravel Homeassistant API

[![Version](https://img.shields.io/packagist/v/mapo-89/laravel-homeassistant-api.svg?label=version&color=03a9f4)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](https://github.com/mapo-89/laravel-homeassistant-api/blob/main/LICENSE.md)
[![PHP](https://img.shields.io/packagist/php-v/mapo-89/laravel-homeassistant-api.svg?logo=php&logoColor=white)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
[![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20.svg?logo=laravel&logoColor=white)](https://laravel.com/)
[![Total Downloads](https://img.shields.io/packagist/dt/mapo-89/laravel-homeassistant-api.svg)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
[![GitHub Actions](https://github.com/mapo-89/laravel-homeassistant-api/actions/workflows/main.yml/badge.svg)](https://github.com/mapo-89/laravel-homeassistant-api/actions/workflows/main.yml)
[![GitHub issues](https://img.shields.io/github/issues/mapo-89/laravel-homeassistant-api.svg)](https://github.com/mapo-89/laravel-homeassistant-api/issues)
[![GitHub stars](https://img.shields.io/github/stars/mapo-89/laravel-homeassistant-api.svg?style=flat)](https://github.com/mapo-89/laravel-homeassistant-api/stargazers)
[![Last commit](https://img.shields.io/github/last-commit/mapo-89/laravel-homeassistant-api.svg)](https://github.com/mapo-89/laravel-homeassistant-api/commits/main)

<a href="https://buymeacoffee.com/mapo"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me a Coffee" height="60"></a>

Laravel integration for the Home Assistant REST and WebSocket APIs, with support
for separate URLs and tokens per installation.

[Deutsche Dokumentation](README.de.md)

## Requirements

- PHP 8.2 or newer
- Laravel 12 or 13 (Laravel 13 requires PHP 8.3 or newer)
- A Home Assistant instance and an access token

## Installation

```bash
composer require mapo-89/laravel-homeassistant-api
```

Laravel discovers the service provider and facade automatically. Configure your
installation in `.env`:

```env
HA_URL=http://homeassistant.local:8123
HA_TOKEN=your_long_lived_token
```

## Configuration

Optionally publish `config/homeassistant-api.php`:

```bash
php artisan vendor:publish --tag=homeassistant-api-config
```

The legacy `--tag=config` tag remains supported.

| Option | Default | Purpose |
| --- | --- | --- |
| `connect_timeout` | `3` | Connection timeout in seconds. |
| `timeout` | `8` | Request timeout in seconds. |
| `cache_responses` | `true` | Cache state lists for five seconds; set to `false` for fresh reads. |

## Usage

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$ha = HomeassistantApi::make();

// For a separate installation, supply both URL and token.
$ha = HomeassistantApi::make([
    'url' => 'https://homeassistant.example.com',
    'token' => 'your_long_lived_token',
]);
```

The same configuration options are available in the array passed to `make()`.

### States and filters

```php
$states = $ha->states()->get(); // State[]; all() is an equivalent alias
$state = $ha->states()->get('light.kitchen'); // State

$lights = $ha->states()
    ->whereDomain('light')
    ->whereState('on')
    ->get();
```

Filters run on the retrieved state list.

| Method | Purpose |
| --- | --- |
| `whereDomain($domain)` | Filter by entity domain. |
| `whereState($state)` | Filter by state. |
| `whereEntityIds($ids)` | Filter by entity IDs. |
| `whereAttribute($key, $value)` | Filter by an attribute value. |
| `get()` | Return matching `State` objects. |
| `first()` | Return the first matching `State`, or `null`. |

### Services

```php
$ha->services()->turnOn('light.kitchen', ['brightness' => 80]);
$ha->services()->turnOff('light.kitchen');
$ha->services()->toggle('light.kitchen');

$response = $ha->services()->call(
    'weather',
    'get_forecasts',
    ['entity_id' => 'weather.home', 'type' => 'daily'],
    returnResponse: true,
);
$forecast = $response['service_response']['weather.home']['forecast'];
```

`call()` normally returns changed states. For services that support or require a
response, use `returnResponse: true` to receive `changed_states` and `service_response`.

### System and configuration

```php
$ha->system()->verify();
$ha->config()->get();
$ha->config()->check();
$ha->config()->setExternalUrl('https://customer.example.com');
```

Setting the external URL requires a Home Assistant administrator token.

### Templates and events

```php
$text = $ha->template()->render('Temperature: {{ states("sensor.kitchen_temperature") }}');

$events = $ha->events()->all();
$ha->events()->fire('my_custom_event', ['source' => 'laravel']);
```

### History and logbook

Both APIs require at least one entity ID. Dates may be `Carbon` or `CarbonImmutable`.

```php
$entities = ['light.kitchen'];
$start = now()->subHours(6);

$history = $ha->history()->get(start: $start, entityIds: $entities);
$flat = $ha->history()->flat(start: $start, entityIds: $entities);
$entries = $ha->logbook()->get(entityIds: $entities, start: $start, end: now());
```

`get()` groups `HistoryState` objects by entity ID; `flat()` returns a single list.
History also accepts `significantOnly` and `minimal` options. Logbook returns
`LogbookEntry` objects.

For Home Assistant endpoint details, see the [REST API documentation](https://developers.home-assistant.io/docs/api/rest).

### Artisan

```bash
php artisan ha:call
php artisan ha:call --entity=light.kitchen
php artisan ha:call light toggle --entity=light.kitchen
```

Artisan uses the static package configuration.

## Upgrading to version 2

- Laravel 10 and 11 are no longer supported.
- `States` and `Services` no longer extend `ApiClient`. Use
  [ApiClient](src/Api/Utils/ApiClient.php) directly for low-level HTTP calls.

API failures throw `HomeAssistantException`; invalid arguments throw `InvalidArgumentException`.

## Development

```bash
composer install
composer qa
```

Tests use simulated API responses and do not need a running Home Assistant instance.
See [CONTRIBUTING](CONTRIBUTING.md) and the [release guide](.release/README.md)
for development, dependency updates and releases. Changes are listed in [CHANGELOG](CHANGELOG.md).

## Security

Report security issues to [info@postler.de](mailto:info@postler.de).

## Credits and license

- [Manuel Postler](https://github.com/mapo-89)
- [All contributors](../../contributors)

Licensed under the [MIT License](LICENSE.md).
