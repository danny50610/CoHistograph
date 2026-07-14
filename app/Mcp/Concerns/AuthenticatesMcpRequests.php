<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait AuthenticatesMcpRequests
{
    protected function authenticatedUser(Request $request): User|Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('未授權：請先以有效的 OAuth access token 登入（scope: mcp:use）。');
        }

        return $user;
    }
}
