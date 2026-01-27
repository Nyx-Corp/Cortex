<?php

namespace Application\{Module}\Controller\Tool;

use Domain\{Domain}\Action\{Model}Edit\Command;
use Domain\{Domain}\Action\{Model}Edit\Handler;
use Domain\{Domain}\Factory\{Model}Factory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\Uid\Uuid;

#[McpTool(name: '{tool_name_edit}', description: 'Update an existing {Model}')]
class {Model}EditTool
{
    public function __construct(
        private readonly Handler $handler,
        private readonly {Model}Factory $factory,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'UUID of the {Model} to update')]
        string $uuid,
        // TODO: Add optional fields for update
    ): array {
        try {
            ${model} = $this->factory->query()
                ->filter(uuid: Uuid::fromString($uuid))
                ->first();

            if (${model} === null) {
                return ['success' => false, 'error' => '{Model} not found'];
            }

            $command = new Command(
                uuid: Uuid::fromString($uuid),
                // TODO: Pass fields
            );
            $response = ($this->handler)($command);

            return [
                'success' => true,
                'uuid' => (string) $response->{model}->uuid,
                'updated' => true,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
