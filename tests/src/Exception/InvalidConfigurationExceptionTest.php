<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Config\Exception;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Config\Exception\InvalidConfigurationException;
use WaffleTests\Commons\Config\AbstractTestCase as TestCase;

#[CoversClass(InvalidConfigurationException::class)]
final class InvalidConfigurationExceptionTest extends TestCase
{
    /**
     * Tests that the exception can be instantiated and is a subclass of WaffleException.
     */
    public function testCanBeInstantiated(): void
    {
        $message = 'Invalid config value';
        $code = 500;
        $exception = new InvalidConfigurationException($message, $code);

        static::assertInstanceOf(InvalidConfigurationException::class, $exception);
        static::assertInstanceOf(Exception::class, $exception);
        static::assertSame($message, $exception->getMessage());
        static::assertSame($code, $exception->getCode());
    }
}
