[![PHP Version Require](http://poser.pugx.org/waffle-commons/config/require/php)](https://packagist.org/packages/waffle-commons/config)
[![PHP CI](https://github.com/waffle-commons/config/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/config/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/config/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/config)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/config/v)](https://packagist.org/packages/waffle-commons/config)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/config/v/unstable)](https://packagist.org/packages/waffle-commons/config)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/config.svg)](https://packagist.org/packages/waffle-commons/config)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/config)](https://github.com/waffle-commons/config/blob/main/LICENSE.md)

Waffle Config Component
=======================

> **Release:** `v0.1.0-beta0`
> **PHP extension required:** `ext-yaml` (the native PECL YAML extension — *not* Symfony/yaml userland)

A strict, typed-getter configuration loader. Reads YAML via the native `ext-yaml` extension with `yaml.decode_php = 0`, eliminating the PHP-deserialisation gadget surface that comes with userland parsers. Environment-specific overlays are applied via `array_replace_recursive`, and `%env(VAR)%` placeholders are resolved at load time.

## 📦 Installation

```bash
composer require waffle-commons/config
```

`ext-yaml` must be available in the PHP runtime. The `waffle-dev` Docker image ships with it pre-installed.

## 🧱 Surface

| Class | Role |
| :--- | :--- |
| `Waffle\Commons\Config\Config` | `final` implementation of `ConfigInterface`. Typed getters: `getInt`, `getString`, `getArray`, `getBool`. |
| `Waffle\Commons\Config\YamlParser` | `final` parser wrapper around `yaml_parse_file()` with safe defaults. |
| `Waffle\Commons\Config\DotEnv` | `.env` file loader writing variables to `getenv()`. |
| `Waffle\Commons\Config\Trait\ParserTrait` | Shared parse helpers. |
| `Waffle\Commons\Config\Exception\InvalidConfigurationException` | Thrown when a key resolves to a value of the wrong type. |

## 🚀 Usage

```php
use Waffle\Commons\Config\Config;
use Waffle\Commons\Contracts\Enum\Failsafe;

$config = new Config(
    configDir: __DIR__ . '/config',
    environment: 'prod',
    failsafe: Failsafe::DISABLED,
);

$port    = $config->getInt('http.port', default: 8080);
$debug   = $config->getBool('app.debug', default: false);
$logs    = $config->getArray('logging.channels', default: []);
$appName = $config->getString('app.name');
```

The constructor signature, verbatim from `src/Config.php`:

```php
public function __construct(
    string $configDir,
    string $environment,
    Failsafe $failsafe = Failsafe::DISABLED,
)
```

## 📁 File layout

```
config/
├── app.yaml          # base, always loaded
├── app_dev.yaml      # environment overlay (applied if env = "dev")
├── app_prod.yaml     # environment overlay
└── app_test.yaml     # environment overlay
```

The base file is loaded first. Then `app_{environment}.yaml` is loaded if it exists, and merged on top of the base via `array_replace_recursive`. `%env(VAR_NAME)%` placeholders anywhere in the resolved tree are expanded by `getenv()`.

## 🛟 Failsafe mode

When `Failsafe::ENABLED` is passed, `Config` skips file loading and seeds a minimal default tree (`waffle.security.level = 1`). This is used by the `ErrorHandlerMiddleware` boot path so that even a totally broken config still allows the error renderer to run.

## 🐘 PHP 8.5 features used

- Typed getters with `?int`/`?string`/`?array`/`?bool` return types.
- `final class Config` and `final class YamlParser` — no subclassing.
- Constructor property promotion.
- `Failsafe` is an enum from `Waffle\Commons\Contracts\Enum\Failsafe` — backed-string semantics for safe defaulting.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/config waffle-dev composer tests
```

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
