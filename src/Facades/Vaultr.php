<?php

namespace Dniccum\Vaultr\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dniccum\Vaultr\Vaultr
 */
class Vaultr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Dniccum\Vaultr\Vaultr::class;
    }
}
