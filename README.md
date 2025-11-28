[![PHP Version Require](http://poser.pugx.org/waffle-commons/config/require/php)](https://packagist.org/packages/waffle-commons/config)
[![PHP CI](https://github.com/waffle-commons/config/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/config/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/config/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/config)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/config/v)](https://packagist.org/packages/waffle-commons/config)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/config/v/unstable)](https://packagist.org/packages/waffle-commons/config)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/config.svg)](https://packagist.org/packages/waffle-commons/config)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/config)](https://github.com/waffle-commons/config/blob/main/LICENSE.md)

Waffle Config Component
=======================

A robust configuration management library for PHP, supporting YAML files and environment variable substitution.

## 📦 Installation

```bash
composer require waffle-commons/config
```

## 🚀 Usage

### Basic Usage

```php
use Waffle\Commons\Config\Config;

// Initialize Config with the path to your configuration directory and current environment
$config = new Config('/path/to/config/dir', 'prod');

// Retrieve a value (supports dot notation)
$dbHost = $config->get('database.host');

// Retrieve with a default value
$debug = $config->get('app.debug', false);
```

### Environment Variables

You can reference environment variables in your YAML files using the `%env(VAR_NAME)%` syntax:

```yaml
# config/app.yaml
database:
  host: '%env(DB_HOST)%'
  password: '%env(DB_PASSWORD)%'
```

### Environment Specifics

The loader automatically merges `app.yaml` with `app_{env}.yaml`. For example, if your environment is `prod`, it will load `app.yaml` and then override values with `app_prod.yaml`.
