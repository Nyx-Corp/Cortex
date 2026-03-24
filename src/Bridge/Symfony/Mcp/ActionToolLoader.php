<?php

namespace Cortex\Bridge\Symfony\Mcp;

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;

/**
 * Bridges Cortex's ActionToolProvider to the MCP SDK Registry.
 *
 * Iterates over domain actions exposed by ActionToolProvider and registers
 * each one as an MCP Tool with a handler that delegates to handleToolCall().
 */
class ActionToolLoader implements LoaderInterface
{
    public function __construct(
        private readonly ActionToolProvider $toolProvider,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        foreach ($this->toolProvider->getTools() as $toolDef) {
            $tool = new Tool(
                name: $toolDef['name'],
                inputSchema: $toolDef['inputSchema'],
                description: $toolDef['description'],
                annotations: null,
            );

            $name = $toolDef['name'];

            $registry->registerTool($tool, function (RequestContext $ctx) use ($name): CallToolResult {
                /** @var CallToolRequest $request */
                $request = $ctx->getRequest();
                $args = $request->arguments;

                $result = $this->toolProvider->handleToolCall($name, $args);

                if (isset($result['error'])) {
                    return CallToolResult::error([
                        new TextContent(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)),
                    ]);
                }

                return new CallToolResult([
                    new TextContent(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)),
                ]);
            }, true);
        }
    }
}
