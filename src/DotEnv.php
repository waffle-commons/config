<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

use Exception;
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

    /**
     * @throws Exception
     */
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

    /**
     * @throws InvalidArgumentException|RuntimeException
     */
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

            // @mago-ignore analysis:possibly-undefined-int-array-index
            [$key, $value] = explode(separator: '=', string: $line, limit: 2);
            $key = trim(string: $key);
            if (!is_string(value: $value)) {
                $value = '';
            }
            $value = trim(string: $value);
            $value = trim(string: $value, characters: '"\'');
            $value = $this->validateAndCast($key, $value);

            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateAndCast(string $key, string $value): string
    {
        if (array_key_exists(key: $key, array: self::EXPECTED_TYPES)) {
            return match (self::EXPECTED_TYPES[$key] ?? $key) {
                'bool' => $this->castBool($key, $value),
                default => $value,
            };
        }

        return $value;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function castBool(string $key, string $value): string
    {
        $normalized = strtolower($value);

        if (!in_array(
            needle: $normalized,
            haystack: ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'],
            strict: true,
        )) {
            throw new InvalidArgumentException(sprintf(
                'Environment variable "%s" must be a boolean. Got "%s".',
                $key,
                $value,
            ));
        }

        return in_array(needle: $normalized, haystack: ['true', '1', 'yes', 'on'], strict: true) ? '1' : '0';
    }
}
