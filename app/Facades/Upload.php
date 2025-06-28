<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Upload extends Facade
{
    /**
     * @method static \App\Services\UploaderService make()
     */
    protected static function getFacadeAccessor(): string
    {
        return 'upload';
    }
}
