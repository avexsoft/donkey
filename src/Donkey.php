<?php

namespace Avexsoft\Donkey;

use Avexsoft\Donkey\Models\Donkey as AvexsoftDonkey;

class Donkey
{
    public function set($key, $value)
    {
        AvexsoftDonkey::create(['key' => $key, 'value' => $value, 'remarks' => '']);
        // return ;
    }

    public function get($key)
    {
        // return AvexsoftDonkey::whereKey($key)->first();
        return AvexsoftDonkey::where('key', $key)->first();
    }
    // Build wonderful things
}
