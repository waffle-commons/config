<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waffle\Commons\Config\DotEnv;

/**
 * Targets the validateAndCast() / castBool() branches missed by DotEnvTest:
 * - APP_DEBUG / DEBUG bool normalization (truthy + falsy variants)
 * - Invalid bool value rejection
 */
#[CoversClass(DotEnv::class)]
#[AllowMockObjectsWithoutExpectations]
final class DotEnvCoverageTest extends TestCase
{
    private string $tempDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/waffle_dotenv_cov_' . uniqid(prefix: 'wf', more_entropy: true);
        mkdir(directory: $this->tempDir, permissions: 0o777, recursive: true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Beta 1: DotEnv no longer touches globals; only the temp dir needs cleanup.
        if (is_dir($this->tempDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $f) {
                ($f->isDir() ? 'rmdir' : 'unlink')($f->getRealPath());
            }
            rmdir($this->tempDir);
        }
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function truthyBoolProvider(): iterable
    {
        yield 'true' => ['true', '1'];
        yield 'TRUE' => ['TRUE', '1'];
        yield '1' => ['1', '1'];
        yield 'yes' => ['yes', '1'];
        yield 'on' => ['on', '1'];
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function falsyBoolProvider(): iterable
    {
        yield 'false' => ['false', '0'];
        yield 'FALSE' => ['FALSE', '0'];
        yield '0' => ['0', '0'];
        yield 'no' => ['no', '0'];
        yield 'off' => ['off', '0'];
    }

    #[DataProvider('truthyBoolProvider')]
    public function testAppDebugNormalizesTruthyBooleanValues(string $raw, string $expected): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_DEBUG={$raw}");

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame($expected, $loaded['APP_DEBUG']);
    }

    #[DataProvider('falsyBoolProvider')]
    public function testAppDebugNormalizesFalsyBooleanValues(string $raw, string $expected): void
    {
        file_put_contents($this->tempDir . '/.env', "APP_DEBUG={$raw}");

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame($expected, $loaded['APP_DEBUG']);
    }

    public function testDebugVariableIsAlsoNormalized(): void
    {
        // Both APP_DEBUG and DEBUG are in EXPECTED_TYPES — verify the second key too.
        file_put_contents($this->tempDir . '/.env', 'DEBUG=yes');

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame('1', $loaded['DEBUG']);
    }

    public function testInvalidBooleanValueIsRejected(): void
    {
        file_put_contents($this->tempDir . '/.env', 'APP_DEBUG=maybe');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_DEBUG');

        new DotEnv($this->tempDir)->load();
    }

    public function testNonBoolVariablesPassThroughUnchanged(): void
    {
        // EXPECTED_TYPES only contains APP_DEBUG / DEBUG; any other key should be passed verbatim.
        file_put_contents($this->tempDir . '/.env', 'OTHER_KEY=raw-value');

        $loaded = new DotEnv($this->tempDir)->load();

        static::assertSame('raw-value', $loaded['OTHER_KEY']);
    }
}
