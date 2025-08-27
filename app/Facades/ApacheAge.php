<?php

namespace App\Facades;

use App\Services\ApacheAgeService;
use Illuminate\Support\Facades\Facade;

class ApacheAge extends Facade
{
    public static function getFacadeAccessor()
    {
        return ApacheAgeService::class;
    }
}
