<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\UserInfoTool;
use Laravel\Mcp\Server;

class McpServer extends Server
{
    /**
     * The MCP server's name.
     */
    protected string $name = 'Mcp Server';

    /**
     * The MCP server's version.
     */
    protected string $version = '0.0.1';

    /**
     * The MCP server's instructions for the LLM.
     */
    protected string $instructions = <<<'MARKDOWN'
        Expense management tools for recording and listing transactions.
        Each transaction includes:
        - Creation date;
        - Category;
        - Description; and
        - Value
    MARKDOWN;

    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        UserInfoTool::class
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}