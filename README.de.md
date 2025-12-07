# 🚀 Laravel Homeassistant API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mapo-89/laravel-homeassistant-api.svg?style=flat-square)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
[![Total Downloads](https://img.shields.io/packagist/dt/mapo-89/laravel-homeassistant-api.svg?style=flat-square)](https://packagist.org/packages/mapo-89/laravel-homeassistant-api)
![GitHub Actions](https://github.com/mapo-89/laravel-homeassistant-api/actions/workflows/main.yml/badge.svg)

Eine einfache Laravel-Integration für die [Homeassistant API](https://developers.home-assistant.io/docs/api/rest). Dieses Paket stellt eine saubere Schnittstelle zur Verfügung, um mit der API zu interagieren.

📖 Diese README ist auch auf [🇬🇧 Englisch](README.md) verfügbar.

---

## 📦 Installation

Installiere das Paket über Composer:

```bash
composer require mapo-89/laravel-homeassistant-api
```

Füge deinen API-Token in deine `.env`-Datei ein:

```env
HA_URL=http://homeassistant.local:8123
HA_TOKEN=your_long_lived_token
```

## ⚡️ Dynamische Konfiguration (URL & Token zur Laufzeit)

Neben der statischen Konfiguration über die config/homeassistant.php unterstützt dieses Package auch dynamische Konfigurationen zur Laufzeit.
Das ist z. B. nützlich, wenn jeder Benutzer einen eigenen Home Assistant Token besitzt oder die Instanz-URL dynamisch gesetzt werden soll.

Optional: Veröffentliche die Konfigurationsdatei:

```bash
php artisan vendor:publish --provider="Mapo89\LaravelHomeassistantApi\HomeassistantApiServiceProvider" --tag="config"
```

---

## ⚙️ Verwendung

```php
use Mapo89\LaravelHomeassistantApi\Facades\HomeassistantApi;

// Beispiel: Abrufen von allen States
$homeassistantApi = HomeassistantApi::make();

//Alternative mit dynamischer COnfig
$config = [
    'url' => 'http://homeassistant.local:8123',
    'token' => 'your_long_lived_token'
];
$homeassistantApi = HomeassistantApi::make($config);
$homeassistantApi->states()->all(); // passe das an deinen Anwendungsfall an
```

> 📚 Die vollständige API-Dokumentation findest du hier: [home-assistant.io](https://developers.home-assistant.io/docs/api/rest)

---

## Artisan Command

Die folgenden Artisan-Befehle verwenden immer die statische Konfiguration aus der `.env` bzw. aus `config/homeassistant.php`:

```bash
# Alle States auflisten
php artisan ha:call


# Einen Service aufrufen (z.B. Licht umschalten) 
php artisan ha:call light toggle --entity=light.wohnzimmer
```

---

## 📒 Changelog

Alle Änderungen findest du im [CHANGELOG](CHANGELOG.md).

---

## 🤝 Mitwirken

Beiträge sind herzlich willkommen! Details findest du in der [CONTRIBUTING](CONTRIBUTING.md)-Datei.

---

## 🔐 Sicherheit

Wenn du sicherheitsrelevante Probleme entdeckst, melde dich bitte **nicht** über den Issue Tracker, sondern direkt per E-Mail an [info@postler.de](mailto:info@postler.de).

---

## 👥 Credits

- [Manuel Postler](https://github.com/mapo-89)  
- [Alle Mitwirkenden](../../contributors)

---

## 📄 Lizenz

Dieses Paket steht unter der [MIT Lizenz](LICENSE.md).

---

## 🛠️ Boilerplate

Erstellt mit dem [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
