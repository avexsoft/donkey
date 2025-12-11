<?php

namespace Avexsoft\Donkey;

use Avexsoft\Donkey\Models\Override;

class Donkey
{
    public function set($key, $value)
    {
        Override::create(['key' => $key, 'value' => $value, 'remarks' => '']);
        // return ;
    }

    public function get($key)
    {
        // return Override::whereKey($key)->first();
        return Override::where('key', $key)->first();
    }
    // Build wonderful things
}
