<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

use InvalidArgumentException;
use RuntimeException;

final readonly class DotEnv
{
    /**
     * @var array<string, string> Expected types for specific env vars.
     */
    private const EXPECTED_TYPES = [
        'APP_DEBUG' => 'bool',
        'DEBUG' => 'bool',
    ];

    public function __construct(
        private string $path,
    ) {}

    public function load(): void
    {
        $files = [
            $this->path . '/.env',
            $this->path . '/.env.local',
        ];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $this->parseFile($file);
        }
    }

    private function parseFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException(sprintf('Impossible de lire le fichier .env : %s', $path));
        }

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $value = trim($value, '"\'');

            $value = $this->validateAndCast($key, $value);

            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    private function validateAndCast(string $key, string $value): string
    {
        if (!isset(self::EXPECTED_TYPES[$key])) {
            return $value;
        }

        return match (self::EXPECTED_TYPES[$key]) {
            'bool' => $this->castBool($key, $value),
            default => $value,
        };
    }

    private function castBool(string $key, string $value): string
    {
        $normalized = strtolower($value);

        if (!in_array($normalized, ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Environment variable "%s" must be a boolean. Got "%s".',
                $key,
                $value,
            ));
        }

        return in_array($normalized, ['true', '1', 'yes', 'on'], true) ? '1' : '0';
    }
}
