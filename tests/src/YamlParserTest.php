<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use RuntimeException;
use Waffle\Commons\Config\YamlParser;
use WaffleTests\Commons\Config\AbstractTestCase as TestCase;

class YamlParserTest extends TestCase
{
    private ?string $tempFile = null;
    private string|false $originalYamlDecodePhp;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        // Sauvegarde de la configuration ini actuelle
        $this->originalYamlDecodePhp = ini_get('yaml.decode_php');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->tempFile && file_exists($this->tempFile)) {
            unlink($this->tempFile);
            $this->tempFile = null;
        }

        // Restauration de la configuration ini
        if ($this->originalYamlDecodePhp !== false) {
            ini_set('yaml.decode_php', $this->originalYamlDecodePhp);
        }
    }

    public function testParseFileWithSimpleStructure(): void
    {
        // Arrange
        $content = <<<YAML
        app:
          name: 'WaffleApp'
          env: test
        database:
          host: localhost
        YAML;
        $this->tempFile = $this->createTempFile($content);
        $parser = new YamlParser();

        $expected = [
            'app' => [
                'name' => 'WaffleApp',
                'env' => 'test',
            ],
            'database' => [
                'host' => 'localhost',
            ],
        ];

        // Act
        $result = $parser->parseFile($this->tempFile);

        // Assert
        static::assertSame($expected, $result);
    }

    public function testParseFileWithCommentsAndEmptyLines(): void
    {
        // Arrange
        $content = <<<YAML
        # Application configuration
        app:
          name: WaffleApp

        # Database connection
        database:
          host: 127.0.0.1
          port: 3306
        YAML;
        $this->tempFile = $this->createTempFile($content);
        $parser = new YamlParser();
        $expected = [
            'app' => ['name' => 'WaffleApp'],
            'database' => ['host' => '127.0.0.1', 'port' => 3306],
        ];

        // Act
        $result = $parser->parseFile($this->tempFile);

        // Assert
        static::assertSame($expected, $result);
    }

    public function testParseFileWithValueContainingSpecialCharacters(): void
    {
        // Arrange
        $content = "url: 'http://example.com?query=1:2'";
        $this->tempFile = $this->createTempFile($content);
        $parser = new YamlParser();
        $expected = ['url' => 'http://example.com?query=1:2'];

        // Act
        $result = $parser->parseFile($this->tempFile);

        // Assert
        static::assertSame($expected, $result);
    }

    public function testParseFileIgnoresInvalidLines(): void
    {
        // Note: Avec l'extension native YAML, une ligne invalide peut causer une erreur de parsing globale
        // ou être interprétée différemment selon la spec YAML 1.1/1.2.
        // Ce test vérifie que le parser gère une structure de liste correcte.
        $content = <<<YAML
        valid_key: valid_value
        list:
          - just a list item 1
          - just a list item 2
          - just a list item 3
        another_valid_key: another_value
        YAML;
        $this->tempFile = $this->createTempFile($content);
        $parser = new YamlParser();
        $expected = [
            'valid_key' => 'valid_value',
            'list' => [
                'just a list item 1',
                'just a list item 2',
                'just a list item 3',
            ],
            'another_valid_key' => 'another_value',
        ];

        // Act
        $result = $parser->parseFile($this->tempFile);

        // Assert
        static::assertSame($expected, $result);
    }

    public function testParseThrowsSecurityExceptionWhenDecodePhpEnabled(): void
    {
        // Simulation d'un environnement non sécurisé
        ini_set('yaml.decode_php', '1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Security Warning');

        $parser = new YamlParser();
        $parser->parseFile('dummy.yaml');
    }

    public function testParseReturnsEmptyArrayIfFileDoesNotExist(): void
    {
        $parser = new YamlParser();
        // Le code attrape l'exception RuntimeException et retourne []
        $result = $parser->parseFile('/path/to/non/existent/file.yaml');

        static::assertSame([], $result);
    }

    public function testParseReturnsEmptyArrayIfFileIsEmpty(): void
    {
        $this->tempFile = $this->createTempFile('');
        $parser = new YamlParser();

        // Un fichier vide lance une RuntimeException dans votre code, qui est catchée
        $result = $parser->parseFile($this->tempFile);

        static::assertSame([], $result);
    }

    public function testParseReturnsEmptyArrayIfYamlIsInvalid(): void
    {
        // Syntaxe YAML invalide
        $this->tempFile = $this->createTempFile('invalid_key: [ unclosed sequence');
        $parser = new YamlParser();

        // L'extension lance un warning, transformé en exception par set_error_handler, puis catché
        $result = $parser->parseFile($this->tempFile);

        static::assertSame([], $result);
    }

    private function createTempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'waffle_config_test_');
        file_put_contents($file, $content);
        return $file;
    }
}
