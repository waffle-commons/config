<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

use RuntimeException;
use Waffle\Commons\Config\Exception\InvalidConfigurationException;
use Waffle\Commons\Contracts\Parser\YamlParserInterface;

/**
 * A very simple, native YAML file parser.
 * It supports basic key-value pairs, nesting, and lists.
 */
final class YamlParser implements YamlParserInterface
{
    /**
     * Parses a YAML file and returns its content as a PHP array.
     *
     * Missing, unreadable, or empty files are lenient — `Config` already
     * gates calls behind `file_exists()`, so `[]` is a legitimate "nothing
     * to load" signal. An actual malformed-YAML parse failure is not: it is
     * surfaced as `InvalidConfigurationException` (this parser's own
     * fail-secure signal, raised from the warning-to-exception error
     * handler below) and MUST propagate uncaught, never be swallowed.
     *
     * @throws InvalidConfigurationException on malformed YAML content.
     */
    #[\Override]
    public function parseFile(string $path): array
    {
        if (ini_get('yaml.decode_php') === '1') {
            throw new \RuntimeException('Security Warning: "yaml.decode_php" is enabled in php.ini. '
            . 'Please set it to 0 to prevent object injection attacks.');
        }

        set_error_handler(static function ($severity, $message, $_file, $_line) {
            /**
             * @var int $severity
             * @var string $message
             */
            throw new InvalidConfigurationException($message, $severity);
        });

        $config = [];
        try {
            if (!is_readable($path) || !is_file($path)) {
                throw new RuntimeException('Failed to parse YAML file.');
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if (!$lines) {
                throw new RuntimeException('Failed to parse YAML file.');
            }

            /** @var array $config */
            $config = yaml_parse_file(filename: $path);

            if (!$config) {
                throw new RuntimeException('Failed to parse YAML file.');
            }
        } catch (RuntimeException $_) {
            return [];
        } finally {
            restore_error_handler();
        }

        return $config;
    }
}
