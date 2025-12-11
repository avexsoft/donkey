<?php

namespace Avexsoft\Donkey\Tests\Feature;

// use Avexsoft\Donkey\Models\Donkey;
use Avexsoft\Donkey\Facades\Donkey;
use Avexsoft\Donkey\Tests\TestbenchTestCase;
use Illuminate\Validation\ValidationException;

class DonkeyTest extends TestbenchTestCase
{
    use \Illuminate\Foundation\Testing\DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['overrides.blacklist' => ['app.*', 'database.*', 'overrides.*']]);
        config(['overrides.whitelist' => ['app.name']]);
    }

    public function test_prevent_store_if_blacklist()
    {
        $this->expectException(ValidationException::class);
        Donkey::create([
            'key'     => 'app.debug',
            'value'   => 'blah',
            'remarks' => '',
        ]);
    }

    public function test_allow_store_if_whitelist()
    {
        Donkey::create([
            'key'     => 'app.name',
            'value'   => 'blah',
            'remarks' => '',
        ]);
        $this->assertDatabaseHas(Donkey::class, [
            'key' => 'app.name',
        ]);
    }

    public function test_allow_store_if_not_blacklist()
    {
        Donkey::create([
            'key'     => 'asdfasdf',
            'value'   => 'blah',
            'remarks' => '',
        ]);
        $this->assertDatabaseHas(Donkey::class, [
            'key' => 'asdfasdf',
        ]);
    }
}
