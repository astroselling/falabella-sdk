<?php

namespace Astroselling\FalabellaSdk\Facades;

use Illuminate\Support\Facades\Facade;

class FalabellaSdk extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'falabellasdk';
    }
}
