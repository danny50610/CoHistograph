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
            return Response::error('Unauthorized: sign in with a valid OAuth access token (scope: mcp:use).');
        }

        return $user;
    }
}
