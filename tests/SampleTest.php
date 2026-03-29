<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Tests;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

final class SampleTest extends TestCase
{
    public function testProjectUsesExpectedNamespacePrefix(): void
    {
        $autoload = require __DIR__ . '/../vendor/autoload.php';

        self::assertInstanceOf(ClassLoader::class, $autoload);

        $prefixes = $autoload->getPrefixesPsr4();

        self::assertArrayHasKey('Frista28\\StreamCryptoPsr7\\', $prefixes);
    }
}
