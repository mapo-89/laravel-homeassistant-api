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

Laravel-Integration für die REST- und WebSocket-APIs von Home Assistant, mit
separaten URLs und Token je Installation.

[English documentation](README.md)

## Voraussetzungen

- PHP ab Version 8.2
- Laravel 12 oder 13 (Laravel 13 benötigt mindestens PHP 8.3)
- Eine Home-Assistant-Instanz und ein Zugriffstoken

## Installation

```bash
composer require mapo-89/laravel-homeassistant-api
```

Laravel registriert den Service Provider und die Fassade automatisch.
Konfiguriere deine Installation in der `.env`:

```env
HA_URL=http://homeassistant.local:8123
HA_TOKEN=your_long_lived_token
```

## Konfiguration

Veröffentliche bei Bedarf `config/homeassistant-api.php`:

```bash
php artisan vendor:publish --tag=homeassistant-api-config
```

Der bisherige Tag `--tag=config` bleibt unterstützt.

| Option | Standard | Zweck |
| --- | --- | --- |
| `connect_timeout` | `3` | Zeitlimit für den Verbindungsaufbau in Sekunden. |
| `timeout` | `8` | Zeitlimit pro Anfrage in Sekunden. |
| `cache_responses` | `true` | States-Listen fünf Sekunden cachen; mit `false` stets neu abrufen. |

## Verwendung

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$ha = HomeassistantApi::make();

// Für eine andere Installation sowohl URL als auch Token übergeben.
$ha = HomeassistantApi::make([
    'url' => 'https://homeassistant.example.com',
    'token' => 'your_long_lived_token',
]);
```

Die Konfigurationsoptionen können auch im Array für `make()` gesetzt werden.

### States und Filter

```php
$states = $ha->states()->get(); // State[]; all() ist ein gleichwertiger Alias
$state = $ha->states()->get('light.kitchen'); // State

$lights = $ha->states()
    ->whereDomain('light')
    ->whereState('on')
    ->get();
```

Die Filter werden auf die abgerufene States-Liste angewendet.

| Methode | Zweck |
| --- | --- |
| `whereDomain($domain)` | Nach Entity-Domain filtern. |
| `whereState($state)` | Nach Zustand filtern. |
| `whereEntityIds($ids)` | Nach Entity-IDs filtern. |
| `whereAttribute($key, $value)` | Nach einem Attributwert filtern. |
| `get()` | Passende `State`-Objekte zurückgeben. |
| `first()` | Das erste passende `State`-Objekt oder `null` zurückgeben. |

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

`call()` liefert normalerweise die geänderten States. Für Dienste, die eine
Antwort unterstützen oder verlangen, liefert `returnResponse: true` die Felder
`changed_states` und `service_response`.

### System und Konfiguration

```php
$ha->system()->verify();
$ha->config()->get();
$ha->config()->check();
$ha->config()->setExternalUrl('https://customer.example.com');
```

Zum Setzen der externen URL wird ein Home-Assistant-Administrator-Token benötigt.

### Templates und Events

```php
$text = $ha->template()->render('Temperature: {{ states("sensor.kitchen_temperature") }}');

$events = $ha->events()->all();
$ha->events()->fire('my_custom_event', ['source' => 'laravel']);
```

### History und Logbook

Beide APIs benötigen mindestens eine Entity-ID. Datumswerte können als `Carbon`
oder `CarbonImmutable` übergeben werden.

```php
$entities = ['light.kitchen'];
$start = now()->subHours(6);

$history = $ha->history()->get(start: $start, entityIds: $entities);
$flat = $ha->history()->flat(start: $start, entityIds: $entities);
$entries = $ha->logbook()->get(entityIds: $entities, start: $start, end: now());
```

`get()` gruppiert `HistoryState`-Objekte nach Entity-ID, `flat()` liefert eine
flache Liste. History unterstützt außerdem die Optionen `significantOnly` und
`minimal`. Logbook gibt `LogbookEntry`-Objekte zurück.

Details zu den Home-Assistant-Endpunkten stehen in der [REST-API-Dokumentation](https://developers.home-assistant.io/docs/api/rest).

### Artisan

```bash
php artisan ha:call
php artisan ha:call --entity=light.kitchen
php artisan ha:call light toggle --entity=light.kitchen
```

Artisan verwendet die statische Paketkonfiguration.

## Umstieg auf Version 2

- Laravel 10 und 11 werden nicht mehr unterstützt.
- `States` und `Services` erben nicht mehr von `ApiClient`. Verwende für direkte
  HTTP-Anfragen den [ApiClient](src/Api/Utils/ApiClient.php).

API-Fehler werfen eine `HomeAssistantException`, ungültige Argumente eine `InvalidArgumentException`.

## Entwicklung

```bash
composer install
composer qa
```

Die Tests verwenden simulierte API-Antworten und benötigen keine laufende
Home-Assistant-Instanz. Hinweise zu Entwicklung, Abhängigkeitsupdates und Releases
stehen in [CONTRIBUTING](CONTRIBUTING.md) und im [Release-Leitfaden](.release/README.md).
Änderungen findest du im [CHANGELOG](CHANGELOG.md).

## Sicherheit

Melde Sicherheitsprobleme an [info@postler.de](mailto:info@postler.de).

## Credits und Lizenz

- [Manuel Postler](https://github.com/mapo-89)
- [Alle Mitwirkenden](../../contributors)

Veröffentlicht unter der [MIT-Lizenz](LICENSE.md).
