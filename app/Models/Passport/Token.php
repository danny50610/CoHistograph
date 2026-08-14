<?php

namespace App\Models\Passport;

use Laravel\Passport\Token as PassportToken;

/**
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property string|null $last_used_user_agent
 */
class Token extends PassportToken
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'scopes' => 'array',
        'revoked' => 'bool',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
}
