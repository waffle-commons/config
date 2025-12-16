<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

use RuntimeException;
use Throwable;
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
     */
    #[\Override]
    public function parseFile(string $path): array
    {
        set_error_handler(function ($severity, $message, $_file, $_line) {
            throw new InvalidConfigurationException($message, $severity);
        });

        $config = [];
        try {
            if (!is_readable($path) || !is_file($path)) {
                throw new RuntimeException("Failed to parse YAML file.");
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if (!$lines) {
                throw new RuntimeException("Failed to parse YAML file.");
            }

            $config = yaml_parse_file(filename: $path);

            if (!$config) {
                throw new RuntimeException("Failed to parse YAML file.");
            }
        } catch (Throwable $_) {
            return [];
        } finally {
            restore_error_handler();
        }

        return $config;
    }
}
