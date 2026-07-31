<?php

use App\Http\Middleware\RestoreDefaultPgsqlSearchPath;
use App\Mcp\Servers\CoHistographServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware(RestoreDefaultPgsqlSearchPath::class)->group(function (): void {
    Mcp::oauthRoutes();
});

Mcp::web('/mcp/cohistograph', CoHistographServer::class)
    ->middleware(['auth:api', 'throttle:mcp']);

Mcp::local('cohistograph', CoHistographServer::class);
