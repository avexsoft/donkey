<?php

namespace Avexsoft\Donkey\Tests\Feature\Models;

use Avexsoft\Donkey\Models\Override;
use Avexsoft\Donkey\Tests\TestbenchTestCase;
use Illuminate\Validation\ValidationException;

class OverrideTest extends TestbenchTestCase
{
    use \Illuminate\Foundation\Testing\DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['override.blacklist' => ['app.*', 'database.*', 'override.*']]);
        config(['override.whitelist' => ['app.name']]);
    }

    public function test_prevent_store_if_blacklist()
    {
        $this->expectException(ValidationException::class);
        Override::create([
            'key'     => 'app.debug',
            'value'   => 'blah',
            'remarks' => '',
        ]);
    }

    public function test_allow_store_if_whitelist()
    {
        Override::create([
            'key'     => 'app.name',
            'value'   => 'blah',
            'remarks' => '',
        ]);
        $this->assertDatabaseHas(Override::class, [
            'key' => 'app.name',
        ]);
    }

    public function test_allow_store_if_not_blacklist()
    {
        Override::create([
            'key'     => 'asdfasdf',
            'value'   => 'blah',
            'remarks' => '',
        ]);
        $this->assertDatabaseHas(Override::class, [
            'key' => 'asdfasdf',
        ]);
    }
}
