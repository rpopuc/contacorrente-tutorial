<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp', \App\Mcp\Servers\McpServer::class)
    ->middleware('auth:api');