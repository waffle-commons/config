<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waffle\Commons\Config\DotEnv;

#[CoversClass(DotEnv::class)]
#[AllowMockObjectsWithoutExpectations]
class DotEnvTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/waffle_dotenv_' . uniqid(prefix: 'wf', more_entropy: true);
        mkdir(directory: $this->tempDir, permissions: 0o777, recursive: true);
    }

    protected function tearDown(): void
    {
        // Cleanup env vars
        putenv('TEST_VAR');
        unset($_ENV['TEST_VAR']);
        unset($_SERVER['TEST_VAR']);
        putenv('ANOTHER_VAR');
        unset($_ENV['ANOTHER_VAR']);
        unset($_SERVER['ANOTHER_VAR']);
        putenv('EXISTING_VAR');
        unset($_ENV['EXISTING_VAR']);
        unset($_SERVER['EXISTING_VAR']);

        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
    }

    /**
     * @throws Exception
     */
    public function testLoadReadsDotEnvFile(): void
    {
        file_put_contents(filename: $this->tempDir . '/.env', data: 'TEST_VAR=foo');

        $dotEnv = new DotEnv($this->tempDir);
        $dotEnv->load();

        static::assertSame('foo', getenv('TEST_VAR'));
        static::assertSame('foo', $_ENV['TEST_VAR']);
        static::assertSame('foo', $_SERVER['TEST_VAR']);
    }

    /**
     * @throws Exception
     */
    public function testLoadReadsDotEnvLocalFile(): void
    {
        file_put_contents(filename: $this->tempDir . '/.env.local', data: 'ANOTHER_VAR=bar');

        $dotEnv = new DotEnv($this->tempDir);
        $dotEnv->load();

        static::assertSame('bar', getenv('ANOTHER_VAR'));
    }

    /**
     * @throws Exception
     */
    public function testLoadDoesNotOverwriteExistingVariables(): void
    {
        putenv('EXISTING_VAR=original');
        $_ENV['EXISTING_VAR'] = 'original';
        $_SERVER['EXISTING_VAR'] = 'original';

        file_put_contents(filename: $this->tempDir . '/.env', data: 'EXISTING_VAR=new');

        $dotEnv = new DotEnv($this->tempDir);
        $dotEnv->load();

        static::assertSame('original', getenv('EXISTING_VAR'));
    }

    /**
     * @throws Exception
     */
    public function testLoadIgnoresCommentsAndInvalidLines(): void
    {
        $content = <<<ENV
            # This is a comment
            VALID_VAR=value
              # Indented comment
            INVALID_LINE_NO_EQUALS

            ENV;
        file_put_contents($this->tempDir . '/.env', $content);

        $dotEnv = new DotEnv($this->tempDir);
        $dotEnv->load();

        static::assertSame('value', getenv('VALID_VAR'));
    }

    /**
     * @throws Exception
     */
    public function testLoadHandlesQuotedValues(): void
    {
        file_put_contents(filename: $this->tempDir . '/.env', data: "QUOTED='some value'");

        $dotEnv = new DotEnv($this->tempDir);
        $dotEnv->load();

        // DotEnv implementation trim(..., '"\'') trims quotes from start/end.
        static::assertSame('some value', getenv('QUOTED'));

        // Cleanup
        putenv('QUOTED');
        unset($_ENV['QUOTED'], $_SERVER['QUOTED']);
    }

    /**
     * @throws Exception
     */
    public function testLoadHandlesMissingFilesGracefully(): void
    {
        // No files created in tempDir
        $dotEnv = new DotEnv($this->tempDir);

        // Should not throw
        $dotEnv->load();

        static::assertTrue(true);
    }
}
