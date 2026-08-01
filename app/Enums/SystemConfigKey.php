<?php

namespace App\Enums;

use App\SystemConfig\HomepageConfig;
use App\SystemConfig\SystemConfigValue;

enum SystemConfigKey: string
{
    case Homepage = 'homepage';

    /**
     * @return class-string<SystemConfigValue>
     */
    public function valueClass(): string
    {
        return match ($this) {
            self::Homepage => HomepageConfig::class,
        };
    }
}
