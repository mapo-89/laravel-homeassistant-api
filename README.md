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

A simple Laravel integration for the [Homeassistant API](https://developers.home-assistant.io/docs/api/rest). This package provides a clean interface to interact with the API.

📖 This README is also available in [🇩🇪 German](README.de.md).

## Features

* Home Assistant REST and WebSocket APIs
* Dynamic URLs and tokens per installation
* State query builder, services, events, history and logbook
* Publishable configuration and the `ha:call` Artisan command

## Requirements

* PHP >= 8.2
* Laravel 12 or 13 (depending on your PHP version)
* A reachable Home Assistant instance and an access token

---

## Installation

Install the package via Composer:

```bash
composer require mapo-89/laravel-homeassistant-api
```

Add your API token to your `.env` file:

```env
HA_URL=http://homeassistant.local:8123
HA_TOKEN=your_long_lived_token
```

## Dynamic configuration (URL & token at runtime)

In addition to static configuration via `config/homeassistant-api.php`, this package also supports dynamic configurations at runtime.
This is useful, for example, if each user has their own Home Assistant token or if the instance URL is to be set dynamically.

Optionally, you can publish the config file:

```bash
php artisan vendor:publish --tag=homeassistant-api-config
```

The legacy `--tag=config` tag remains supported. Laravel discovers the service provider and facade automatically.

---

## Usage

> 📚 The complete API documentation can be found here: [home-assistant.io](https://developers.home-assistant.io/docs/api/rest)

### Initialization

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$homeassistantApi = HomeassistantApi::make();

//Alternative with dynamic configuration
$config = [
    'url' => 'http://homeassistant.local:8123',
    'token' => 'your_long_lived_token'
];
$homeassistantApi = HomeassistantApi::make($config);
```

---

### System API

```php
// Check if the API is working
$homeassistantApi->system()->verify();
```

---

### Config API

```php
// Output current configuration
$homeassistantApi->config()->get();

// Check current configuration
$homeassistantApi->config()->check();
```

---


### Set the external URL

```php
$homeassistantApi->config()->setExternalUrl('https://customer.example.com');
```

This authenticates through the Home Assistant WebSocket API, updates only
`external_url`, and reads the configuration back to verify it. The token must
belong to a Home Assistant administrator. TLS verification stays enabled, and
connection and response times are bounded. Use `HomeassistantApi::make($config)`
for separate URLs and tokens per installation.

### States API

```php
$homeassistantApi->states()->all(); // adapt this to your use case
```


---

### Template API

More information about [templating](https://www.home-assistant.io/docs/configuration/templating)

```php
// renders a template
$homeassistantApi->template()->render(<<<'TEMPLATE'
    Paulus is at {{ states('device_tracker.paulus') }}!
    TEMPLATE
);
```

---

### Events API

Interaction with the Home Assistant event system.

```php
// Retrieve all available events
$events = $homeassistantApi->events()->all();

// Trigger custom event
$homeassistantApi->events()->fire('my_custom_event', [
    'source' => 'laravel',
    'user_id' => 123,
]);
```

**Supported endpoints**
- `GET /api/events`
- `POST /api/events/{event_type}`

---

### History API

Access historical state changes of entities.

```php
// Grouped history by entity
$history = $homeassistantApi->history()->get(
    start: now()->subHours(12),
    entityIds: ['light.kitchen', 'sensor.temperature'],
    significantOnly: true
);

// Access per entity
$history['light.kitchen']; // HistoryState[]
```

**Flat output (ideal for charts & analytics)**

```php
$flat = $homeassistantApi->history()->flat(
    start: now()->subHours(6),
    entityIds: ['light.kitchen', 'sensor.temperature'],
);

```

**Supported endpoints**
- `GET /api/history/period`
- `POST /api/history/period/{timestamp}`

---

### Logbook API

Access the Home Assistant logbook – ideal for user activities, automations, and system events.

> ⚠️ The logbook API requires at least one `entityId`.

```php
$entries = $homeassistantApi->logbook()->get(
    start: now()->subHours(6),
    entityIds: ['light.kitchen'],
    end: now()
);
```

**Supported endpoint**
- `GET /api/logbook`

---

## Query Builder API (States)

Home Assistant does not support server-side filtering of states.
This package therefore provides a **query builder-like API**
that can be used to filter states on the client side – inspired by Laravel Eloquent.

---

## Example

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$states = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->whereState('on')
    ->get();
```

---

## Available query methods

### `whereDomain(string $domain)`

Filters by the domain of an entity (e.g., `light`, `sensor`, `switch`).

```php
$lights = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->get();
```

---

### `whereState(string $state)`

Filters by the state of an entity (e.g., `on`, `off`, `unavailable`).

```php
$active = HomeassistantApi::make()
    ->states()
    ->whereState('on')
    ->get();
```

---

### `whereEntityIds(array $entityIds)`

Filters by a list of specific entity IDs.

```php
$states = HomeassistantApi::make()
    ->states()
    ->whereEntityIds([
        'light.kitchen',
        'sensor.temperature',
    ])
    ->get();
```

---

### `whereAttribute(string $key, mixed $value)`

Filters by attributes of an entity.

```php
$lights = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->whereAttribute('brightness', 255)
    ->get();
```

---

### `get()`

Returns all filtered states as an array of `State` DTOs.

```php
$states = HomeassistantApi::make()
    ->states()->get();
```

---

### `first()`

Returns the first matching state or `null`.

```php
$state = HomeassistantApi::make()
    ->states()
    ->whereEntityIds(['light.kitchen'])
    ->first();
```

---

## Artisan Command

The following Artisan commands always use the static configuration from `.env` or `config/homeassistant-api.php`:

```bash
# List all states
php artisan ha:call

# Call a service (e.g., switch lights) (in implementation)
php artisan ha:call light toggle --entity=light.livingroom
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

---

## Security

If you discover any security issues, please do **not** use the issue tracker. Instead, email us directly at [info@postler.de](mailto:info@postler.de).

---

## Credits

- [Manuel Postler](https://github.com/mapo-89)
- [All Contributors](../../contributors)

---

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.

---

## Package tooling

Configuration and command registration use [Spatie Laravel Package Tools](https://github.com/spatie/laravel-package-tools).

## Testing

```bash
composer install
composer test
```

Integration tests cover configuration merging, both publish tags, `ha:call`
registration, and the shared service and facade instance. WebSocket tests use
mocks and do not require a running Home Assistant instance.

## Shared release workflow

Commit types, explicit release triggers, and grouped release notes follow the
[shared release standard](.release/README.md). Ordinary pushes do not publish
production versions. Existing builds, artifact handling, and deployment approvals
remain project-specific.

## Release branch workflow

Feature branches start from main and are squash-merged into release/X.Y.Z.
Alternatively or additionally, commit directly on release/X.Y.Z; these commits
remain separate and do not require squashing. Ordinary commits do not rewrite
version metadata or CHANGELOG.md. Once the release scope is complete, run
`node .release/branches.mjs prepare` once to collect all changes on that branch.
If more commits follow, refresh preparation and checks together at finalization,
rather than after each commit. Then merge the prepared release into main
through a reviewed PR using a merge commit titled `🚀 Release: X.Y.Z`.
Direct pushes/local merges to main and administrator bypasses are forbidden.
Publish the tested merge commit, then remove the fully integrated release branch
after successful publication and verification. Create a new branch per version.
The prepared changelog supplies the published release notes; no post-release
changelog commit is created. See [.release/README.md](.release/README.md).

## Automated dependency maintenance

Dependabot proposes weekly dependency updates. Reviewed updates are retargeted to
the active release branch, tested, and squash-merged through their PRs.
See [.release/README.md](.release/README.md#dependency-updates) for the policy
and activation requirements. Dependency PRs never authorize a release.
