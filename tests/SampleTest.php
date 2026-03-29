<?php

declare(strict_types=1);

namespace Frista28\StreamCryptoPsr7\Tests;

use PHPUnit\Framework\TestCase;

final class SampleTest extends TestCase
{
    public function testProjectUsesExpectedNamespacePrefix(): void
    {
        $autoload = require __DIR__ . '/../vendor/autoload.php';
        $prefixes = $autoload->getPrefixesPsr4();

        $this->assertArrayHasKey('Frista28\\StreamCryptoPsr7\\', $prefixes);
    }
}
