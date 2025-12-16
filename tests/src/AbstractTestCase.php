<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use PHPUnit\Framework\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Waffle\Commons\Config\Config;
use Waffle\Commons\Contracts\Enum\Failsafe;

abstract class AbstractTestCase extends BaseTestCase
{
    protected string $testConfigDir;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->testConfigDir = sys_get_temp_dir() . '/waffle_' . bin2hex(random_bytes(4));
        // Create a temporary config directory for isolated testing
        if (!is_dir($this->testConfigDir)) {
            mkdir($this->testConfigDir, 0o777, true);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up the temporary config directory safely
        $this->cleanupTestConfig();
    }

    protected function cleanupTestConfig(): void
    {
        $dirToDelete = APP_ROOT . DIRECTORY_SEPARATOR . APP_CONFIG;
        if (is_dir($dirToDelete)) {
            $this->recursiveDelete($dirToDelete);
        }
        if (is_dir($this->testConfigDir)) {
            $this->recursiveDelete($this->testConfigDir);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $action = $fileinfo->isDir() && !$fileinfo->isLink() ? 'rmdir' : 'unlink';
            $action($fileinfo->getRealPath());
        }
        unset($files);

        rmdir($dir);
    }

    protected function createTestConfigFile(
        int $securityLevel = 10,
        string $controllerPath = 'tests/src/Helper/Controller',
        string $servicePath = 'tests/src/Helper/Service',
    ): void {
        $yamlContent = <<<YAML
        waffle:
          security:
            level: {$securityLevel}
          paths:
            controllers: '{$controllerPath}'
            services: '{$servicePath}'
        YAML;
        file_put_contents($this->testConfigDir . '/app.yaml', $yamlContent);

        $yamlContentTest = <<<YAML
        waffle:
          test_specific_key: true
        YAML;
        file_put_contents($this->testConfigDir . '/app_test.yaml', $yamlContentTest);
    }

    protected function createAndGetConfig(
        int $securityLevel = 10,
        string $controllerPath = 'tests/src/Helper/Controller',
        string $servicePath = 'tests/src/Helper/Service',
        Failsafe $failsafe = Failsafe::DISABLED,
    ): Config {
        $this->createTestConfigFile(
            securityLevel: $securityLevel,
            controllerPath: $controllerPath,
            servicePath: $servicePath,
        );

        return new Config(
            configDir: $this->testConfigDir,
            environment: 'dev',
            failsafe: $failsafe,
        );
    }
}
