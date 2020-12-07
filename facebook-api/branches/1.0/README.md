# Pollen Solutions FacebookApi Component

[![Latest Version](https://img.shields.io/badge/release-1.0.0-blue?style=for-the-badge)](https://svn.tigreblanc.fr/pollen-solutions/facebook-api/tags/1.0.0)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-green?style=for-the-badge)](LICENSE.md)

**FacebookApi** Component.

## Installation

```bash
composer require pollen-solutions/facebook-api
```

## Setup

### Declaration

```php
// config/app.php
return [
      //...
      'providers' => [
          //...
          \Pollen\FacebookApi\FacebookApiServiceProvider::class,
          //...
      ];
      // ...
];
```

### Configuration

```php
// config/facebook-api.php
// @see /vendor/pollen-solutions/facebook-api/config/facebook-api.php
return [
      //...

      // ...
];
```
