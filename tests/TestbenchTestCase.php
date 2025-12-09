<?php

namespace Avexsoft\Donkey\Tests;

use Orchestra\Testbench\TestCase as Testbench;

abstract class TestbenchTestCase extends Testbench
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Avexsoft\Donkey\DonkeyServiceProvider::class,
        ];
    }
}
