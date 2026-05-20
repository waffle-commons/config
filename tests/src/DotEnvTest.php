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
        // Beta 1: DotEnv no longer touches globals, so there is nothing process-env
        // to unset here. Only the temp dir cleanup remains.
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

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame('foo', $loaded['TEST_VAR']);
    }

    /**
     * @throws Exception
     */
    public function testLoadReadsDotEnvLocalFile(): void
    {
        file_put_contents(filename: $this->tempDir . '/.env.local', data: 'ANOTHER_VAR=bar');

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame('bar', $loaded['ANOTHER_VAR']);
    }

    /**
     * Within DotEnv, the first file (.env) wins when both .env and .env.local
     * declare the same key. Process-env precedence is the AppKernelFactory's job.
     *
     * @throws Exception
     */
    public function testEnvFileWinsOverEnvLocalForSameKey(): void
    {
        file_put_contents(filename: $this->tempDir . '/.env', data: 'EXISTING_VAR=from_env');
        file_put_contents(filename: $this->tempDir . '/.env.local', data: 'EXISTING_VAR=from_local');

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame('from_env', $loaded['EXISTING_VAR']);
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

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame('value', $loaded['VALID_VAR']);
        static::assertArrayNotHasKey('INVALID_LINE_NO_EQUALS', $loaded);
    }

    /**
     * @throws Exception
     */
    public function testLoadHandlesQuotedValues(): void
    {
        file_put_contents(filename: $this->tempDir . '/.env', data: "QUOTED='some value'");

        $loaded = new DotEnv($this->tempDir)->load();

        // DotEnv implementation trim(..., '"\'') trims quotes from start/end.
        static::assertSame('some value', $loaded['QUOTED']);
    }

    /**
     * @throws Exception
     */
    public function testLoadHandlesMissingFilesGracefully(): void
    {
        // No files created in tempDir
        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame([], $loaded);
    }

    /**
     * @throws Exception
     */
    public function testLoadDoesNotTouchProcessEnvironment(): void
    {
        // Beta-1 contract: putenv() / $_ENV / $_SERVER must NOT be mutated.
        $sentinelEnv = $_ENV;
        $sentinelServer = $_SERVER;

        file_put_contents($this->tempDir . '/.env', "BETA1_ISOLATION_PROBE=value\n");

        new DotEnv($this->tempDir)->load();

        static::assertSame($sentinelEnv, $_ENV, '$_ENV must remain untouched');
        static::assertSame($sentinelServer, $_SERVER, '$_SERVER must remain untouched');
        static::assertFalse(getenv('BETA1_ISOLATION_PROBE'), 'putenv() must not be called');
    }
}
