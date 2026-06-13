<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pure file parser for `.env` / `.env.local`.
 *
 * Beta-1 hardening (Roadmap Beta 1 Phase 0): this class no longer mutates the
 * global PHP environment — no `putenv()`, no writes to `$_ENV` / `$_SERVER`.
 * `putenv()` is not thread-safe under FrankenPHP worker mode and could leak
 * between concurrent requests; the new contract returns a read-only map that
 * the caller (typically `AppKernelFactory`) merges with the process environment
 * and injects into {@see Config}.
 *
 * Precedence within DotEnv: when both files declare the same key, the first
 * occurrence wins (`.env` beats `.env.local`). Process-env precedence over
 * DotEnv is the caller's responsibility (e.g. `array_merge($dotenv->load(), getenv() ?: [])`).
 */
final readonly class DotEnv
{
    /**
     * @var array<string, string> Expected types for specific env vars.
     */
    private const array EXPECTED_TYPES = [
        'APP_DEBUG' => 'bool',
        'DEBUG' => 'bool',
    ];

    public function __construct(
        private string $path,
    ) {}

    /**
     * Parses `.env` and `.env.local` (in that order) and returns the merged map.
     *
     * @return array<string, string> Parsed environment variables. First file wins on conflict.
     * @throws Exception
     */
    public function load(): array
    {
        $files = [
            $this->path . '/.env',
            $this->path . '/.env.local',
        ];

        $result = [];
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $this->parseFile($file, $result);
        }
        return $result;
    }

    /**
     * @param array<string, string> $result
     * @throws InvalidArgumentException|RuntimeException
     */
    private function parseFile(string $path, array &$result): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new RuntimeException(sprintf('Impossible de lire le fichier .env : %s', $path));
        }

        foreach ($lines as $line) {
            if (str_starts_with(mb_trim($line), '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            // @mago-ignore analysis:possibly-undefined-int-array-index
            [$key, $value] = explode(separator: '=', string: $line, limit: 2);
            $key = mb_trim(string: $key);
            if (!is_string(value: $value)) {
                $value = '';
            }
            $value = mb_trim(string: $value);
            $value = trim(string: $value, characters: '"\'');
            $value = $this->validateAndCast($key, $value);

            if (!array_key_exists($key, $result)) {
                $result[$key] = $value;
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
