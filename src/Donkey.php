<?php

namespace Avexsoft\Donkey;

use Avexsoft\Donkey\Models\Override;

class Donkey
{
    public function set($key, $value, $remarks = null): static
    {
        $data['value'] = $value;
        if ($remarks) {
            $data['remarks'] = $remarks;
        }

        Override::updateOrCreate(['key' => $key], $data);

        return $this;
    }

    public function get($key)
    {
        // return Override::whereKey($key)->first();
        return Override::where('key', $key)->first();
    }
    // Build wonderful things
}
