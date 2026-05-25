<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use PHPUnit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use ReflectionException;
use Waffle\Commons\Config\Config;
use Waffle\Commons\Config\Exception\InvalidConfigurationException;
use WaffleTests\Commons\Config\AbstractTestCase as TestCase;

// Added for testing protected method
// Added use statement
// Added use statement

#[CoversClass(Config::class)] // Added CoversClass
class ConfigTestGetters extends TestCase
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
    public function testGetReturnsCorrectValueForExistingKey(): void
    {
        // Act
        // Uses the default app.yaml created by createTestConfigFile in AbstractTestCase
        $config = $this->createAndGetConfig();

        // Assert
        static::assertSame(10, $config->getInt(key: 'waffle.security.level'));
        static::assertSame('tests/src/Helper/Controller', $config->getString(key: 'waffle.paths.controllers'));
        static::assertSame('tests/src/Helper/Service', $config->getString(key: 'waffle.paths.services'));
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testGetReturnsDefaultValueForNonexistentKey(): void
    {
        // Act
        $config = $this->createAndGetConfig();

        // Assert
        $getDefault = $config->getString(key: 'app.nonexistent', default: 'default_value');
        static::assertSame('default_value', $getDefault);
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testGetReturnsNullForNonexistentKeyWhenNoDefaultIsProvided(): void
    {
        // Act
        $config = $this->createAndGetConfig();

        // Assert
        static::assertNull($config->getString(key: 'app.nonexistent'));
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testLoadHandlesNonexistentConfigFileGracefully(): void
    {
        // Act
        // Use a non-existent environment to ensure no app_*.yaml is found
        $config = new Config($this->testConfigDir, 'nonexistent_env');

        // Assert
        static::assertNull($config->getString(key: 'anything')); // Assuming app.yaml also doesn't exist or is empty
    }

    /**
     * @throws ReflectionException|ExpectationFailedException
     */
    public function testProtectedGetMethodRetrievesValue(): void
    {
        $config = $this->createAndGetConfig(); // Uses default app.yaml

        // Use Reflection to call the protected get method
        $reflection = new ReflectionClass(Config::class);
        $method = $reflection->getMethod('get');

        // Call protected method 'get'
        $value = $method->invoke($config, 'waffle.security.level');
        static::assertSame(10, $value);

        $nestedValue = $method->invoke($config, 'waffle.paths.controllers');
        static::assertSame('tests/src/Helper/Controller', $nestedValue);

        $nonExistentValue = $method->invoke($config, 'app.nonexistent.key');
        static::assertNull($nonExistentValue);
    }

    /**
     * @throws InvalidConfigurationException|Exception
     */
    public function testResolveEnvPlaceholders(): void
    {
        // Beta 1: env values come from the injected $env array, not from process env.
        // NON_EXISTENT_VAR is intentionally not provided.
        // @mago-ignore lint:no-literal-password — these are test fixtures, not real secrets.
        $config = new Config(configDir: $this->testConfigDir, environment: 'test_env', env: [
            'TEST_API_KEY' => 'abcdef12345',
            'NESTED_TEST_VAR' => 'nested_value',
        ]);

        // Assert that placeholders were replaced
        static::assertSame('abcdef12345', $config->getString('service.api_key'));
        static::assertSame('https://example.com', $config->getString('service.url')); // Unchanged
        static::assertSame('nested_value', $config->getString('service.nested.value'));

        // Assert that a missing env variable results in null
        static::assertNull($config->getString('service.missing_var'));
    }
}
