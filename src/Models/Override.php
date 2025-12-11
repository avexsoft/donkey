<?php

namespace Avexsoft\Donkey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Override extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'remarks',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($_this) {
            $_this->validate();
        });

        static::saved(function ($_this) {
            $_this->saveToFile();
        });

        static::deleted(function ($_this) {
            $_this->saveToFile();
        });
    }

    public function saveToFile()
    {
        $config = $this->whereIsActive(true)->pluck('value', 'key')->toArray();
        $path = storage_path('config');
        if (! file_exists($path)) {
            mkdir($path);
        }
        file_put_contents($path.'/values.json', json_encode($config, JSON_PRETTY_PRINT));

        Artisan::call('config:clear');
    }

    public function validate()
    {
        $blacklistConfigs = config('override.blacklist');
        $whitelistConfigs = config('override.whitelist');

        $validForCreate = true;
        foreach ($blacklistConfigs as $blacklistConfig) {
            if (Str::is($blacklistConfig, $this->key)) {
                $validForCreate = false;
            }
        }

        foreach ($whitelistConfigs as $whitelistConfig) {
            if (Str::is($whitelistConfig, $this->key)) {
                $validForCreate = true;
            }
        }

        if (! $validForCreate) {
            throw ValidationException::withMessages([
                'key' => 'Config Key Is Not Allowed',
            ]);
        }
    }
}
