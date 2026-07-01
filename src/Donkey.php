<?php

namespace Avexsoft\Donkey;

use Avexsoft\Donkey\Models\Override;

class Donkey
{
    /**
     * Let the packages specific the kind of keys they are expecting the user to configure
     *
     * @param  string  $key  the key that is used/referenced in our code
     * @param  string  $defaultValue  default value added
     * @param  string  $remarks  give the user some idea what this is for
     */
    public function expect($key, $remarks = null, $defaultValue = null): static
    {
        $data = [];
        $data['value'] = $defaultValue;
        if ($remarks) {
            $data['remarks'] = $remarks;
        }

        Override::firstOrCreate(['key' => $key], $data);

        return $this;
    }

    /**
     * Set the values of the keys
     *
     * @param  string  $key  the key that is used/referenced in our code
     * @param  string  $value  default value added
     * @param  string  $remarks  give the user some idea what this is for
     */
    public function set($key, $value, $remarks = null): static
    {
        $data = [];
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
