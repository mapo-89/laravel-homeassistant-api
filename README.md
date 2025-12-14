# 🚀 Laravel Homeassistant API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mapo-89/laravel-homeassistant-api.svg?style=flat-square)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
[![Total Downloads](https://img.shields.io/packagist/dt/mapo-89/laravel-homeassistant-api.svg?style=flat-square)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
![GitHub Actions](https://github.com/mapo-89/laravel-homeassistant-api/actions/workflows/main.yml/badge.svg)

A simple Laravel integration for the [Homeassistant API](https://developers.home-assistant.io/docs/api/rest). This package provides a clean interface to interact with the API.

📖 This README is also available in [🇩🇪 German](README.de.md).

---

## 📦 Installation

Install the package via Composer:

```bash
composer require mapo-89/laravel-homeassistant-api
```

Add your API token to your `.env` file:

```env
HA_URL=http://homeassistant.local:8123
HA_TOKEN=your_long_lived_token
```

## ⚡️ Dynamic configuration (URL & token at runtime)

In addition to static configuration via config/homeassistant.php, this package also supports dynamic configurations at runtime.
This is useful, for example, if each user has their own Home Assistant token or if the instance URL is to be set dynamically.

Optionally, you can publish the config file:

```bash
php artisan vendor:publish --provider="Mapo89\LaravelHomeassistantApi\HomeassistantApiServiceProvider" --tag="config"
```

---

## ⚙️ Usage

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

// Example: Fetch all states
$homeassistantApi = HomeassistantApi::make();

//Alternative with dynamic configuration
$config = [
    'url' => 'http://homeassistant.local:8123',
    'token' => 'your_long_lived_token'
];
$homeassistantApi = HomeassistantApi::make($config);
$homeassistantApi->states()->all(); // customize this based on your needs
```

> 📚 Full API documentation available at: [home-assistant.io](https://developers.home-assistant.io/docs/api/rest)

---

## Query Builder API (States)

Home Assistant does not support server-side filtering of states.
This package therefore provides a **query builder-like API**
that can be used to filter states on the client side – inspired by Laravel Eloquent.

---

## ✨ Example

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$states = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->whereState('on')
    ->get();
```

---

## 🧱 Available query methods

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

The following Artisan commands always use the static configuration from `.env` or `config/homeassistant.php`:

```bash
# List all states
php artisan ha:call

# Call a service (e.g., switch lights) (in implementation)
php artisan ha:call light toggle --entity=light.livingroom
```

---

## 📒 Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

---

## 🔐 Security

If you discover any security issues, please do **not** use the issue tracker. Instead, email us directly at [info@postler.de](mailto:info@postler.de).

---

## 👥 Credits

- [Manuel Postler](https://github.com/mapo-89)  
- [All Contributors](../../contributors)

---

## 📄 License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.

---

## 🛠️ Laravel Package Boilerplate

Generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
