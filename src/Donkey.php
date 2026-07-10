<?php

namespace Avexsoft\Donkey;

use Avexsoft\Donkey\Models\Override;

class Donkey
{
    /**
     * Let the packages specify the kind of keys they are expecting the user to configure
     * Call this in the `boot()` of service providers
     *
     * @param  string  $key  Laravel config() key that is used/referenced in our code
     * @param  string  $remarks  give the user some idea what this is for, always overwritten
     * @param  string  $defaultValue  provide a default value (if record does not already exists)
     */
    public function expect($key, $remarks = null, $defaultValue = null): static
    {
        $existing = Override::where('key', $key)->first();

        if ($existing) {
            if ($remarks) {
                $existing->update(['remarks' => $remarks]);
            }
        } else {
            $data = ['key' => $key, 'value' => $defaultValue];
            if ($remarks) {
                $data['remarks'] = $remarks;
            }
            Override::create($data);
        }

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
