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

Eine einfache Laravel-Integration für die [Homeassistant API](https://developers.home-assistant.io/docs/api/rest). Dieses Paket stellt eine saubere Schnittstelle zur Verfügung, um mit der API zu interagieren.

📖 Diese README ist auch auf [🇬🇧 Englisch](README.md) verfügbar.

## Funktionen

* REST- und WebSocket-Zugriff auf Home Assistant
* Dynamische URL und Token je Installation
* States mit Query-Builder, Services, Events, History und Logbook
* Konfiguration und Artisan-Befehl `ha:call`

## Voraussetzungen

* PHP >= 8.2
* Laravel 12 oder 13 (abhängig von der PHP-Version)
* Eine erreichbare Home-Assistant-Instanz und ein Zugriffstoken

---

## Installation

Installiere das Paket über Composer:

```bash
composer require mapo-89/laravel-homeassistant-api
```

Füge deinen API-Token in deine `.env`-Datei ein:

```env
HA_URL=http://homeassistant.local:8123
HA_TOKEN=your_long_lived_token
```

## Dynamische Konfiguration (URL & Token zur Laufzeit)

Neben der statischen Konfiguration über die `config/homeassistant-api.php` unterstützt dieses Package auch dynamische Konfigurationen zur Laufzeit.
Das ist z. B. nützlich, wenn jeder Benutzer einen eigenen Home Assistant Token besitzt oder die Instanz-URL dynamisch gesetzt werden soll.

Optional: Veröffentliche die Konfigurationsdatei:

```bash
php artisan vendor:publish --tag=homeassistant-api-config
```

Der bisherige Tag `--tag=config` bleibt unterstützt. Laravel registriert den Service Provider und die Fassade automatisch.

REST-Zeitlimits und der States-Cache lassen sich in der veröffentlichten Konfiguration
oder im Array für `HomeassistantApi::make($config)` einstellen:

```php
'connect_timeout' => 3, // Sekunden bis zum Verbindungsaufbau
'timeout' => 8, // Sekunden pro REST-Anfrage
'cache_responses' => true, // States-Listen fünf Sekunden je URL und Token cachen
```

Mit `cache_responses => false` wird jede States-Liste neu abgerufen. Dynamische
Konfigurationen müssen weiterhin eine eigene URL und einen Token enthalten.

---

## Verwendung

> 📚 Die vollständige API-Dokumentation findest du hier: [home-assistant.io](https://developers.home-assistant.io/docs/api/rest)

### Initialisierung

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$homeassistantApi = HomeassistantApi::make();

//Alternative mit dynamischer Konfiguration
$config = [
    'url' => 'http://homeassistant.local:8123',
    'token' => 'your_long_lived_token'
];
$homeassistantApi = HomeassistantApi::make($config);
```

---

### System API

```php
// Prüfen, ob die API funktioniert
$homeassistantApi->system()->verify();
```

---

### Config API

```php
// Gebe aktuelle Konfiguration aus
$homeassistantApi->config()->get();

// Prüfe aktuelle Konfiguration
$homeassistantApi->config()->check();
```

---

### Externe URL setzen

```php
$homeassistantApi->config()->setExternalUrl('https://kunde.example.com');
```

Die Methode authentifiziert sich über die WebSocket-API, ändert ausschließlich
`external_url` und liest den Wert anschließend zur Bestätigung zurück. Der Token
muss zu einem Home-Assistant-Administrator gehören. TLS-Prüfung bleibt aktiv;
Verbindungs- und Antwortzeiten sind begrenzt. Mit `HomeassistantApi::make($config)`
lassen sich URL und Token je Installation separat übergeben.

### States API

```php
$homeassistantApi->states()->all(); // passe das an deinen Anwendungsfall an
```

---

### Template API

Mehr Infos zum [Templating](https://www.home-assistant.io/docs/configuration/templating)

```php
// rendert ein Template
$homeassistantApi->template()->render(<<<'TEMPLATE'
    Paulus is at {{ states('device_tracker.paulus') }}!
    TEMPLATE
);
```

`templates()->render(...)` bleibt als gleichwertiger Aufruf unterstützt.

---

### Events API

Interaktion mit dem Home Assistant Event-System.

```php
// Alle verfügbaren Events abrufen
$events = $homeassistantApi->events()->all();

// Custom Event auslösen
$homeassistantApi->events()->fire('my_custom_event', [
    'source' => 'laravel',
    'user_id' => 123,
]);
```

**Unterstützte Endpunkte**
- `GET /api/events`
- `POST /api/events/{event_type}`

---

### History API

Zugriff auf historische State-Änderungen von Entities.

```php
// Gruppierte Historie nach Entity
$history = $homeassistantApi->history()->get(
    start: now()->subHours(12),
    entityIds: ['light.kitchen', 'sensor.temperature'],
    significantOnly: true
);

// Zugriff pro Entity
$history['light.kitchen']; // HistoryState[]
```

**Flache Ausgabe (ideal für Charts & Analytics)**

```php
$flat = $homeassistantApi->history()->flat(
    start: now()->subHours(6),
    entityIds: ['light.kitchen', 'sensor.temperature'],
);

```

`significantOnly` ist standardmäßig `false` und fordert alle Änderungen an.
Mit `minimal: true` liefert Home Assistant für Zwischenstände nur State und
Änderungszeit. Die Entity-ID wird aus der Gruppe ergänzt; `lastUpdated` verwendet
dann `lastChanged`, und fehlende Attribute werden als leeres Array dargestellt.

**Unterstützte Endpunkte**
- `GET /api/history/period`
- `GET /api/history/period/{timestamp}`

---

### Logbook API

Zugriff auf das Home Assistant Logbook – ideal für Benutzeraktivitäten, Automationen und Systemereignisse.

> ⚠️ Die Logbook-API erfordert mindestens eine `entityId`.

```php
$entries = $homeassistantApi->logbook()->get(
    start: now()->subHours(6),
    entityIds: ['light.kitchen'],
    end: now()
);
```

**Unterstützter Endpunkt**
- `GET /api/logbook/{timestamp}`

---


## Query-Builder API (States)

Home Assistant unterstützt kein serverseitiges Filtern von States.
Dieses Package stellt daher eine **Query-Builder-artige API** zur Verfügung,
mit der States clientseitig gefiltert werden können – inspiriert von Laravel Eloquent.

---

## Beispiel

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

$states = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->whereState('on')
    ->get();
```

---

## Verfügbare Query-Methoden

### `whereDomain(string $domain)`

Filtert nach der Domain einer Entity (z. B. `light`, `sensor`, `switch`).

```php
$lights = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->get();
```

---

### `whereState(string $state)`

Filtert nach dem State einer Entity (z. B. `on`, `off`, `unavailable`).

```php
$active = HomeassistantApi::make()
    ->states()
    ->whereState('on')
    ->get();
```

---

### `whereEntityIds(array $entityIds)`

Filtert nach einer Liste konkreter Entity-IDs.

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

Filtert nach Attributen einer Entity.

```php
$lights = HomeassistantApi::make()
    ->states()
    ->whereDomain('light')
    ->whereAttribute('brightness', 255)
    ->get();
```

---

### `get()`

Gibt alle gefilterten States als Array von `State` DTOs zurück.

```php
$states = HomeassistantApi::make()
    ->states()->get();
```

---

### `first()`

Gibt den ersten passenden State oder `null` zurück.

```php
$state = HomeassistantApi::make()
    ->states()
    ->whereEntityIds(['light.kitchen'])
    ->first();
```

---

## Artisan Command

Die folgenden Artisan-Befehle verwenden immer die statische Konfiguration aus der `.env` bzw. aus `config/homeassistant-api.php`:

```bash
# Alle States auflisten
php artisan ha:call

# Einen Service aufrufen (z.B. Licht umschalten)
php artisan ha:call light toggle --entity=light.wohnzimmer
```

---

## Changelog

Alle Änderungen findest du im [CHANGELOG](CHANGELOG.md).

---

## Mitwirken

Beiträge sind herzlich willkommen! Details findest du in der [CONTRIBUTING](CONTRIBUTING.md)-Datei.

---

## Sicherheit

Wenn du sicherheitsrelevante Probleme entdeckst, melde dich bitte **nicht** über den Issue Tracker, sondern direkt per E-Mail an [info@postler.de](mailto:info@postler.de).

---

## Credits

- [Manuel Postler](https://github.com/mapo-89)
- [Alle Mitwirkenden](../../contributors)

---

## Lizenz

Dieses Paket steht unter der [MIT Lizenz](LICENSE.md).

---

## Package-Tools

Konfiguration und Befehle werden mit [Spatie Laravel Package Tools](https://github.com/spatie/laravel-package-tools) registriert.

## Tests

```bash
composer install
composer test
```

Die Integrationstests prüfen Konfigurations-Merging, beide Publish-Tags,
die Registrierung von `ha:call` sowie die gemeinsame Service- und Fassadeninstanz.
Die WebSocket-Tests verwenden Mocks und benötigen keine laufende Home-Assistant-Instanz.
REST-Tests prüfen mit Laravel-HTTP-Fakes die CLI-Ausgabe, Templates, History,
Logbook-Zeiträume, Zeitlimits, Cache-Trennung und Fehlerbehandlung. Die
WebSocket-Tests decken auch Neustartbedarf, Registry-Gruppen und Token-Operationen ab.

## Releases und Abhängigkeitsupdates

Commit-Kategorien und Release-Regeln folgen dem [gemeinsamen Release-Standard](.release/README.md).
Feature-Branches starten von `main` und werden einzeln in `release/X.Y.Z` gesquasht.
Direkte kategorisierte Commits auf dem Release-Branch sind ebenfalls erlaubt.
Normale Commits ändern weder Versionen noch Changelog. Erst zur Finalisierung
wird `node .release/branches.mjs prepare` gesammelt ausgeführt.
Der vorbereitete Release gelangt über einen geprüften Pull Request mit einem
Merge-Commit `🚀 Release: X.Y.Z` nach `main`. Die Veröffentlichung setzt erfolgreiche
CI für genau diesen Merge-Commit voraus; bestehende Tags bleiben unverändert.
Nach erfolgreicher Veröffentlichung und Prüfung wird der vollständig integrierte
Release-Branch entfernt. Normale Pushes veröffentlichen keinen Release.

Dependabot schlägt montags Updates für Composer und GitHub Actions vor.
Minor- und Patch-Updates werden je Ökosystem gruppiert, Major-Updates separat geprüft.
Ausgewählte Update-PRs werden auf den aktiven Release-Branch umgestellt, geprüft
und dort per Squash-Merge integriert. Bot-PRs werden nicht automatisch gemergt
und autorisieren keinen Release. Die Konfiguration wird nach Übernahme nach `main`
aktiv; gegebenenfalls müssen Versionsupdates in GitHub aktiviert werden.
