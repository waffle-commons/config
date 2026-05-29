[![PHP Version Require](http://poser.pugx.org/waffle-commons/config/require/php)](https://packagist.org/packages/waffle-commons/config)
[![PHP CI](https://github.com/waffle-commons/config/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/config/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/config/graph/badge.svg?token=d74ac62a-7872-4035-8b8b-bcc3af1991e0)](https://codecov.io/gh/waffle-commons/config)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/config/v)](https://packagist.org/packages/waffle-commons/config)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/config/v/unstable)](https://packagist.org/packages/waffle-commons/config)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/config.svg)](https://packagist.org/packages/waffle-commons/config)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/config)](https://github.com/waffle-commons/config/blob/main/LICENSE.md)

Waffle Config Component
=======================

> **Release:** `v0.1.0-beta2` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md) &nbsp;|&nbsp; *Beta-1 hardening retained: no process-env mutation*
> **PHP extension required:** `ext-yaml` (the native PECL YAML extension — *not* Symfony/yaml userland)

A strict, typed-getter configuration loader. Reads YAML via the native `ext-yaml` extension with `yaml.decode_php = 0`, eliminating the PHP-deserialisation gadget surface that comes with userland parsers. Environment-specific overlays are applied via `array_replace_recursive`, and `%env(VAR)%` placeholders are resolved at load time **against a read-only env registry injected through the constructor** — never against `getenv()` or `$_ENV` directly (Beta 1 hardening for FrankenPHP worker-mode safety).

## 📦 Installation

```bash
composer require waffle-commons/config
```

`ext-yaml` must be available in the PHP runtime. The `waffle-dev` Docker image ships with it pre-installed.

## 🧱 Surface

| Class | Role |
| :--- | :--- |
| `Waffle\Commons\Config\Config` | `final` implementation of `ConfigInterface`. Typed getters: `getInt`, `getString`, `getArray`, `getBool`. Accepts a constructor `array $env = []` registry consulted for `%env(VAR)%` resolution. |
| `Waffle\Commons\Config\YamlParser` | `final` parser wrapper around `yaml_parse_file()` with safe defaults. |
| `Waffle\Commons\Config\DotEnv` | **Beta 1**: pure `.env` / `.env.local` parser. `load(): array<string,string>` returns the parsed map; no longer mutates `putenv()`, `$_ENV`, or `$_SERVER`. |
| `Waffle\Commons\Config\Trait\ParserTrait` | Shared parse helpers. |
| `Waffle\Commons\Config\Exception\InvalidConfigurationException` | Thrown when a key resolves to a value of the wrong type. |

## 🚀 Usage

```php
use Waffle\Commons\Config\Config;
use Waffle\Commons\Config\DotEnv;
use Waffle\Commons\Contracts\Enum\Failsafe;

// Build the env registry from .env + process env (rightmost wins → OS beats .env).
$envRegistry = array_merge(
    (new DotEnv(__DIR__))->load(),
    getenv() ?: [],
);

$config = new Config(
    configDir:   __DIR__ . '/config',
    environment: 'prod',
    failsafe:    Failsafe::DISABLED,
    env:         $envRegistry,
);

$port    = $config->getInt('http.port', default: 8080);
$debug   = $config->getBool('app.debug', default: false);
$logs    = $config->getArray('logging.channels', default: []);
$appName = $config->getString('app.name');
```

The constructor signature, verbatim from `src/Config.php`:

```php
/**
 * @param array<string, string> $env Read-only env registry consulted when
 *        resolving `%env(VAR)%` placeholders. Defaults to an empty map.
 */
public function __construct(
    string $configDir,
    string $environment,
    Failsafe $failsafe = Failsafe::DISABLED,
    array $env = [],
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

The base file is loaded first. Then `app_{environment}.yaml` is loaded if it exists, and merged on top of the base via `array_replace_recursive`. `%env(VAR_NAME)%` placeholders anywhere in the resolved tree are expanded against the constructor-injected `$env` registry (see [Environment registry](#-environment-registry-beta-1) below).

## 🌱 Environment registry (Beta 1)

Beta 1 removes all process-env mutation from this component. The contract is now:

1. **`DotEnv::load(): array<string,string>`** — pure file parser. Reads `.env` and `.env.local` (first file wins on conflict) and returns the parsed map. Boolean-typed keys (`APP_DEBUG`, `DEBUG`) are validated + normalized to `'1'`/`'0'`; anything else for those keys throws `InvalidArgumentException`. No globals are mutated.
2. **`Config(..., array $env = [])`** — the caller builds the env registry and injects it. `%env(VAR)%` resolution reads from `$this->env[$name] ?? null` — never from `getenv()`, `$_ENV`, or `$_SERVER`.

### Canonical wiring

```php
$envRegistry = array_merge(
    (new DotEnv($root))->load(),   // left: .env / .env.local
    getenv() ?: [],                // right: OS / Docker / K8s
);
```

`array_merge` is **rightmost-wins** on string keys, so the **process environment beats `.env`** on collision. This matches the Twelve-Factor convention and the implicit precedence of the legacy DotEnv (which silently skipped any key already in `$_ENV` / `$_SERVER`). Flip the order to make `.env` win.

> **Type-normalization asymmetry.** DotEnv normalizes `APP_DEBUG`/`DEBUG` booleans; `getenv()` does not. So `APP_DEBUG=yes` in `.env` becomes `'1'`, but the same value exported by the OS becomes `'yes'` — which then fails `Config::getBool('app.debug')` if the YAML uses `'%env(APP_DEBUG)%'`. Either export canonical `true`/`false` values, or normalize `$processEnv` before merging, or use YAML boolean literals instead of `%env()%` for bool keys.

See the [how-to guide](../../documentation/how-to/configuration.md) and the [reference doc](../../documentation/reference/config.md) for the full discussion.

## 🛟 Failsafe mode

When `Failsafe::ENABLED` is passed, `Config` skips file loading and seeds a minimal default tree (`waffle.security.level = 1`). This is used by the `ErrorHandlerMiddleware` boot path so that even a totally broken config still allows the error renderer to run.

## 🐘 PHP 8.5 features used

- Typed getters with `?int`/`?string`/`?array`/`?bool` return types.
- `final class Config` and `final class YamlParser` — no subclassing.
- Constructor property promotion.
- `Failsafe` is an enum from `Waffle\Commons\Contracts\Enum\Failsafe` — backed-string semantics for safe defaulting.

## 🧭 Architectural boundary (`mago guard`)

An active dependency **perimeter** is enforced on every CI run by `vendor/bin/mago guard` (bundled into `composer mago`; zero baselines). The rules live in [`mago.toml`](./mago.toml) under `[guard.perimeter]` — a forbidden `use` statement fails the build, not a reviewer.

Production code under `Waffle\Commons\Config` may depend **only** on:

- `Waffle\Commons\Config\**` — itself
- `Waffle\Commons\Contracts\**` — the shared contracts package, the **only** Waffle dependency permitted
- `Psr\**` — PSR interfaces
- `@global` + `Psl\**` — PHP core (including `ext-yaml`) and the PHP Standard Library

Test code under `WaffleTests\Commons\Config` is unrestricted (`@all`). Structural rules are guarded too: interfaces must be named `*Interface`, `Exception\**` classes must end in `*Exception`, and any `Enum\**` namespace may hold only `enum` declarations.

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`, never directly through one another.

## 🧪 Testing

```bash
docker exec -w /waffle-commons/config waffle-dev composer tests
```

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).
