<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

use Waffle\Commons\Config\Exception\InvalidConfigurationException;
use Waffle\Commons\Contracts\Config\ConfigInterface;
use Waffle\Commons\Contracts\Enum\Failsafe;

final class Config implements ConfigInterface
{
    private array $parameters = [];

    /**
     * @param array<string, string> $env Read-only env registry consulted when
     *        resolving `%env(VAR)%` placeholders. Inject the merged DotEnv +
     *        process environment from `AppKernelFactory`. Defaults to an empty
     *        map — `%env(...)%` placeholders then resolve to `null`.
     */
    public function __construct(
        string $configDir,
        string $environment,
        Failsafe $failsafe = Failsafe::DISABLED,
        private readonly array $env = [],
    ) {
        if ($failsafe === Failsafe::ENABLED) {
            $this->loadFailsafeDefaults();
            return;
        }

        $this->loadConfigurationFiles($configDir, $environment);
    }

    private function loadFailsafeDefaults(): void
    {
        // Provide minimal, safe defaults for the exception handler to work.
        $this->parameters = [
            'waffle' => [
                'security' => [
                    'level' => 1, // Lowest security level to avoid issues.
                ],
            ],
        ];
    }

    private function loadConfigurationFiles(string $configDir, string $environment): void
    {
        $parser = new YamlParser();
        $baseConfigFile = $configDir . '/app.yaml';
        $envConfigFile = $configDir . '/app_' . $environment . '.yaml';

        if (file_exists($baseConfigFile)) {
            $this->parameters = $parser->parseFile($baseConfigFile);
        }

        if (file_exists($envConfigFile)) {
            $envConfig = $parser->parseFile($envConfigFile);
            $this->parameters = array_replace_recursive($this->parameters, $envConfig);
        }

        $this->resolveEnvPlaceholders($this->parameters);
    }

    /**
     * @throws InvalidConfigurationException
     */
    #[\Override]
    public function getInt(string $key, ?int $default = null): ?int
    {
        /** @var array|string|int|bool|null $value */
        $value = $this->get(key: $key);

        if (null === $value) {
            return $default;
        }

        if (!is_int($value)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration key "%s" expects type "int", but got "%s".',
                $key,
                gettype($value),
            ));
        }

        return $value;
    }

    /**
     * @throws InvalidConfigurationException
     */
    #[\Override]
    public function getString(string $key, ?string $default = null): ?string
    {
        /** @var array|string|int|bool|null $value */
        $value = $this->get(key: $key);

        if (null === $value) {
            return $default;
        }

        if (!is_string($value)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration key "%s" expects type "string", but got "%s".',
                $key,
                gettype($value),
            ));
        }

        return $value;
    }

    /**
     * @throws InvalidConfigurationException
     */
    #[\Override]
    public function getArray(string $key, ?array $default = null): ?array
    {
        /** @var array|string|int|bool|null $value */
        $value = $this->get(key: $key);

        if (null === $value) {
            return $default;
        }

        if (!is_array($value)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration key "%s" expects type "array", but got "%s".',
                $key,
                gettype($value),
            ));
        }

        return $value;
    }

    /**
     * @throws InvalidConfigurationException
     */
    #[\Override]
    public function getBool(string $key, ?bool $default = null): ?bool
    {
        /** @var array|string|int|bool|null $value */
        $value = $this->get(key: $key);

        if (null === $value) {
            return $default;
        }

        if (!is_bool($value)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration key "%s" expects type "boolean", but got "%s".',
                $key,
                gettype($value),
            ));
        }

        return $value;
    }

    private function get(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $this->parameters;

        foreach ($keys as $k) {
            // FIX: Add a guard clause to ensure we only traverse arrays.
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            /** @var array|string|int|bool $value */
            $value = $value[$k];
        }

        return $value;
    }

    private function resolveEnvPlaceholders(array &$config): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $this->resolveEnvPlaceholders($value);
                // Write the recursively-resolved sub-tree back. Iterating by key
                // (not by reference) keeps each slot from being constrained to a
                // single type, so the string branch can replace it cleanly.
                $config[$key] = $value;

                continue;
            }

            $matches = [];
            if (is_string($value) && preg_match('/^%env\((.*)\)%$/', $value, $matches) === 1) {
                // Beta-1: env values come from the injected registry — no `getenv()`
                // / `$_ENV` reads at request time. Unknown placeholders resolve to null.
                $config[$key] = $this->env[$matches[1] ?? ''] ?? null;
            }
        }
    }
}
