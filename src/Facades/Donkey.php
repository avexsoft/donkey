<?php

namespace Avexsoft\Donkey\Facades;

use Illuminate\Support\Facades\Facade;

class Donkey extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'donkey';
    }
}
