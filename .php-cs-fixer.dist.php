<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['vendor']);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@auto' => true,
    ])
    ->setFinder($finder);