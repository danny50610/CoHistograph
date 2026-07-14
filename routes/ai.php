<?php

use App\Mcp\Servers\CoHistographServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/cohistograph', CoHistographServer::class)
    ->middleware(['auth:api', 'throttle:mcp']);

Mcp::local('cohistograph', CoHistographServer::class);
