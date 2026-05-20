<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Config\Config;
use Waffle\Commons\Config\Exception\InvalidConfigurationException;
use Waffle\Commons\Contracts\Enum\Failsafe;
use WaffleTests\Commons\Config\AbstractTestCase as TestCase;

/**
 * Targets the happy paths + branches missed by the existing per-getter test files:
 * - Config::loadConfigurationFiles (base + environment-specific YAML merging)
 * - Config::resolveEnvPlaceholders (real env values, missing env, nested arrays)
 * - getInt/getString/getArray/getBool default-return and successful-value branches
 */
#[CoversClass(Config::class)]
final class ConfigCoverageTest extends TestCase
{
    /** Writes a YAML file inside the test config dir and returns the env name. */
    private function writeYaml(string $env, string $content): string
    {
        $path = $this->testConfigDir . '/app_' . $env . '.yaml';
        file_put_contents($path, $content);
        return $env;
    }

    public function testBaseAndEnvironmentFilesAreMerged(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "shared:\n  a: base\n  b: base\n");
        $env = $this->writeYaml('prod', "shared:\n  b: prod\n");

        $config = new Config(configDir: $this->testConfigDir, environment: $env);

        // The base value for `a` is preserved; `b` is overridden by the env-specific file.
        static::assertSame('base', $config->getString('shared.a'));
        static::assertSame('prod', $config->getString('shared.b'));
    }

    public function testGetStringReturnsValueWhenStringIsPresent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "app:\n  name: 'Waffle'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame('Waffle', $config->getString('app.name'));
    }

    public function testGetStringReturnsDefaultWhenKeyAbsent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "app:\n  name: 'Waffle'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame('fallback', $config->getString('app.missing', 'fallback'));
        static::assertNull($config->getString('app.missing'));
    }

    public function testGetIntReturnsValueWhenIntIsPresent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "app:\n  port: 8080\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame(8080, $config->getInt('app.port'));
    }

    public function testGetIntReturnsDefaultWhenKeyAbsent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "app:\n  port: 8080\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame(80, $config->getInt('app.missing', 80));
        static::assertNull($config->getInt('app.missing'));
    }

    public function testGetIntThrowsForWrongType(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "app:\n  port: 'not-an-int'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expects type "int"');

        $config->getInt('app.port');
    }

    public function testGetArrayReturnsValueWhenArrayIsPresent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "db:\n  hosts:\n    - 'a'\n    - 'b'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame(['a', 'b'], $config->getArray('db.hosts'));
    }

    public function testGetArrayReturnsDefaultWhenKeyAbsent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "db: {}\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame(['x'], $config->getArray('db.missing', ['x']));
        static::assertNull($config->getArray('db.missing'));
    }

    public function testGetArrayThrowsForWrongType(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "db:\n  hosts: 'not-array'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expects type "array"');

        $config->getArray('db.hosts');
    }

    public function testGetBoolReturnsValueWhenBoolIsPresent(): void
    {
        // Avoid YAML 1.1 boolean keywords as KEY names (on/off/yes/no would coerce).
        file_put_contents($this->testConfigDir . '/app.yaml', "flags:\n  enabled: true\n  disabled: false\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertTrue($config->getBool('flags.enabled'));
        static::assertFalse($config->getBool('flags.disabled'));
    }

    public function testGetBoolReturnsDefaultWhenKeyAbsent(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "flags: {}\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertTrue($config->getBool('flags.missing', true));
        static::assertNull($config->getBool('flags.missing'));
    }

    public function testGetBoolThrowsForWrongType(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "flags:\n  enabled: 'maybe'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('expects type "boolean"');

        $config->getBool('flags.enabled');
    }

    public function testGetTraversesNestedKeysAndReturnsNullForMissingPath(): void
    {
        file_put_contents($this->testConfigDir . '/app.yaml', "deep:\n  nested:\n    key: 'value'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused');

        static::assertSame('value', $config->getString('deep.nested.key'));
        static::assertNull($config->getString('deep.nested.missing'));
        static::assertNull($config->getString('deep.missing.anything'));
        // Traversing INTO a scalar — must return null without exception.
        static::assertNull($config->getString('deep.nested.key.extra'));
    }

    public function testEnvPlaceholderIsResolvedWhenVariableExists(): void
    {
        // Beta 1: env values come from the injected $env array, not from process env.
        file_put_contents($this->testConfigDir . '/app.yaml', "service:\n  token: '%env(WAFFLE_TEST_ENV_VAR)%'\n");
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused', env: [
            'WAFFLE_TEST_ENV_VAR' => 'resolved',
        ]);

        static::assertSame('resolved', $config->getString('service.token'));
    }

    public function testEnvPlaceholderIsResolvedRecursivelyForNestedArrays(): void
    {
        file_put_contents(
            $this->testConfigDir . '/app.yaml',
            "service:\n  nested:\n    token: '%env(WAFFLE_NESTED_VAR)%'\n",
        );
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused', env: [
            'WAFFLE_NESTED_VAR' => 'deep-value',
        ]);

        static::assertSame('deep-value', $config->getString('service.nested.token'));
    }

    public function testEnvPlaceholderResolvesToNullWhenVariableMissing(): void
    {
        // The injected $env map omits the placeholder key — must resolve to null.
        file_put_contents(
            $this->testConfigDir . '/app.yaml',
            "service:\n  token: '%env(WAFFLE_DEFINITELY_UNSET_VAR)%'\n",
        );
        $config = new Config(configDir: $this->testConfigDir, environment: 'unused', env: []);

        static::assertNull($config->getString('service.token'));
    }

    public function testFailsafeShortCircuitsLoadingPipeline(): void
    {
        // Even if a malformed YAML lives in the test config dir, Failsafe::ENABLED
        // must bypass it and return baked-in defaults.
        file_put_contents($this->testConfigDir . '/app.yaml', "this: is: bad: yaml\n");

        $config = new Config(configDir: $this->testConfigDir, environment: 'unused', failsafe: Failsafe::ENABLED);

        static::assertSame(1, $config->getInt('waffle.security.level'));
    }
}
