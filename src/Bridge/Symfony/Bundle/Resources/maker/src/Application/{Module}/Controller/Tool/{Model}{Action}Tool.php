<?php

namespace Application\{Module}\Controller\Tool;

use Domain\{Domain}\Action\{Model}{Action}\Command;
use Domain\{Domain}\Action\{Model}{Action}\Handler;
use Domain\{Domain}\Factory\{Model}Factory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\Uid\Uuid;

#[McpTool(name: '{tool_name}', description: 'TODO: Describe this tool')]
class {Model}{Action}Tool
{
    public function __construct(
        private readonly Handler $handler,
        private readonly {Model}Factory $factory,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'UUID of the {Model}')]
        string $uuid,
        // TODO: Add parameters matching Command properties
    ): array {
        try {
            ${model} = $this->factory->query()
                ->filter(uuid: Uuid::fromString($uuid))
                ->first();

            if (${model} === null) {
                return ['success' => false, 'error' => '{Model} not found'];
            }

            $command = new Command({model}: ${model});
            $response = ($this->handler)($command);

            return [
                'success' => true,
                'uuid' => (string) $response->{model}->uuid,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
