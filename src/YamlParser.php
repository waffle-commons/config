<?php

declare(strict_types=1);

namespace Waffle\Commons\Config;

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
        return yaml_parse_file(filename: $path);
    }
}
