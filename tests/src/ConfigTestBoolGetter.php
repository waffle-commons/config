<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use PHPUnit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Config\Config;
use Waffle\Commons\Config\Exception\InvalidConfigurationException;
use WaffleTests\Commons\Config\AbstractTestCase as TestCase;

// Added for testing protected method
// Added use statement
// Added use statement

#[CoversClass(Config::class)] // Added CoversClass
class ConfigTestBoolGetter extends TestCase
{
    private ?string $tempYamlFileBool = null; // For bool test
    private ?string $tempYamlFileArray = null; // For array test
    private ?string $tempYamlFileEnv = null; // For env test
    private array $tempFilesCreated = []; // Keep track of temp files

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp(); // Creates the test config directory and default app.yaml

        $yamlContentBool = <<<YAML
            app:
              feature_enabled: true
              another_feature: false
              not_a_bool: 'maybe'
            YAML;
        $this->tempYamlFileBool = $this->testConfigDir . '/app_test_bool.yaml';
        file_put_contents($this->tempYamlFileBool, $yamlContentBool);

        $yamlContentArray = <<<YAML
            database:
              connections:
                - mysql
                - pgsql
              not_an_array: 'just_string'
            YAML;
        $this->tempYamlFileArray = $this->testConfigDir . '/app_test_array.yaml';
        file_put_contents($this->tempYamlFileArray, $yamlContentArray);

        $yamlContentEnv = <<<YAML
            service:
              api_key: '%env(TEST_API_KEY)%'
              url: 'https://example.com'
              missing_var: '%env(NON_EXISTENT_VAR)%'
              nested:
                value: '%env(NESTED_TEST_VAR)%'
            YAML;
        $this->tempYamlFileEnv = $this->testConfigDir . '/app_test_env.yaml';
        file_put_contents($this->tempYamlFileEnv, $yamlContentEnv);
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Clean up additional yaml files
        if ($this->tempYamlFileBool && file_exists($this->tempYamlFileBool)) {
            unlink($this->tempYamlFileBool);
            $this->tempYamlFileBool = null;
        }
        if ($this->tempYamlFileArray && file_exists($this->tempYamlFileArray)) {
            unlink($this->tempYamlFileArray);
            $this->tempYamlFileArray = null;
        }
        if ($this->tempYamlFileEnv && file_exists($this->tempYamlFileEnv)) {
            unlink($this->tempYamlFileEnv);
            $this->tempYamlFileEnv = null;
        }
        // Clean up any other temp files created directly in tests
        foreach ($this->tempFilesCreated as $file) {
            if (!file_exists($file)) {
                continue;
            }

            unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testGetBoolReturnsCorrectValue(): void
    {
        $config = new Config($this->testConfigDir, 'test_bool'); // Loads app_test_bool.yaml
        static::assertTrue($config->getBool('app.feature_enabled'));
        static::assertFalse($config->getBool('app.another_feature'));
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testGetBoolReturnsDefaultValue(): void
    {
        $config = new Config($this->testConfigDir, 'test_bool');
        static::assertTrue($config->getBool('app.nonexistent_bool', true)); // Default true
        static::assertFalse($config->getBool('app.nonexistent_bool')); // Default false (method default)
        static::assertFalse($config->getBool('app.nonexistent_bool', false)); // Explicit default false
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testGetBoolThrowsExceptionForInvalidType(): void
    {
        static::expectException(InvalidConfigurationException::class);
        // Note: The error message was corrected to "bool"
        static::expectExceptionMessage('Configuration key "app.not_a_bool" expects type "boolean", but got "string".');

        $config = new Config($this->testConfigDir, 'test_bool');
        $config->getBool('app.not_a_bool');
    }
}
