<?php

declare(strict_types=1);

namespace Codeberry\Beads\Tests;

use Codeberry\Beads\BeadsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BeadsServiceProvider::class,
        ];
    }
}
