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

Alternatively, you can load the token dynamically (e.g., from a database). In your `AppServiceProvider` inside the `boot()` method:

```php
use Illuminate\Support\Facades\Config;
use App\Models\Integration;

public function boot()
{
    Config::set('Homeassistant-api.api_token', Integration::getApiToken('Homeassistant'));
}
```

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
$homeassistantApi->states()->all(); // customize this based on your needs
```

> 📚 Full API documentation available at: [home-assistant.io](https://developers.home-assistant.io/docs/api/rest)

---

## Artisan Command

List all states or call a service:

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
