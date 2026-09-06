# Changelog — waffle-commons/config

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta6] — 2026-09

**Theme: fail-secure configuration parsing.**

### Fixed
- `YamlParser` no longer swallows its own fail-secure exception: malformed YAML propagates `InvalidConfigurationException` instead of silently degrading to an empty (permissive-default) configuration (Beta6 audit FIX-01).

### Changed
- **Worker-safety audit coverage.** This component was never audited by `wfl igor`: it had no `igor-php/igor-php` in `require-dev`, so the ecosystem runner silently skipped it for four releases. It now ships `igor.json`, the `composer igor` script, and the dev dependency, and is part of the 0-KO gate.
- **Worker-safety annotation.** `Config::$parameters` is declared `#[WorkerSafe(scope: 'boot-time')]`: it is populated exclusively from the constructor and never written per request.

### Documentation
- The README now links into the central Diátaxis documentation tree (DOC-02).

## [0.1.0-beta5] — 2026-07-08

**Theme: zero-suppression analysis.**

### Changed
- `Config::resolveEnvPlaceholders()` now iterates the tree by key (`$config[$key] = …`) instead of by reference, removing the `@mago-ignore analysis:reference-constraint-violation` suppression. The recursively-resolved sub-tree is written back per slot, so the string branch can replace a value cleanly without the reference being pinned to a single type (POLICY-05).
- `DotEnv::load()` now splits each line into `$parts` and reads `$parts[1] ?? ''`, with the pre-existing `=` guard keeping the access total. This drops the `@mago-ignore analysis:possibly-undefined-int-array-index` suppression — both analyzer ignores in this component are now gone (POLICY-05).
- Enabled the `cyclomatic-complexity` linter rule in [`mago.toml`](./mago.toml) with `threshold = 50`.

## [0.1.0-beta4] — 2026-06-13

### Changed
- Lockstep version bump with the Beta-4 wave (security hardening, worker-mode diagnostics, and DX tooling landed in sibling components). No behavioural changes in this component since `0.1.0-beta3`.

## [0.1.0-beta3] — 2026-06-07

**Theme: identity federation & stateless persistence (ecosystem wave).**

### Changed
- Lockstep version bump; `composer.lock` refreshed with the beta-3 dependency wave.

## [0.1.0-beta2.1] — 2026-05-30

### Changed
- Lockstep re-tag of `0.1.0-beta2` (umbrella housekeeping patch) — no source changes in this component.

## [0.1.0-beta2] — 2026-05-29

### Changed
- Lockstep version bump only. No behavioural changes since `0.1.0-beta1`.
- `composer.lock` refreshed to align with the ecosystem-wide dependency wave.

## [0.1.0-beta1]

See the umbrella [CHANGELOG](../CHANGELOG.md#010-beta1) for the full Beta-1 narrative — process-environment mutation removed (`putenv` / `$_ENV` / `$_SERVER`), `DotEnv::load()` now returns a read-only map injected into `Config`, resolving the FrankenPHP worker-mode thread-safety hazard.
