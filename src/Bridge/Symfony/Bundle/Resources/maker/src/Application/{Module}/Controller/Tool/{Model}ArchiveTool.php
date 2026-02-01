<?php

namespace Application\{Module}\Controller\Tool;

use Domain\{Domain}\Action\{Model}Archive\Command;
use Domain\{Domain}\Action\{Model}Archive\Handler;
use Domain\{Domain}\Factory\{Model}Factory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\Uid\Uuid;

#[McpTool(name: '{tool_name_archive}', description: 'Archive or restore a {Model}')]
class {Model}ArchiveTool
{
    public function __construct(
        private readonly Handler $handler,
        private readonly {Model}Factory $factory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        #[Schema(description: 'UUID of the {Model}')]
        string $uuid,
        #[Schema(description: 'Archive (true) or restore (false)')]
        bool $archive = true,
    ): array {
        try {
            ${model} = $this->factory->query()
                ->filter(uuid: Uuid::fromString($uuid))
                ->first();

            if (null === ${model}) {
                return ['success' => false, 'error' => '{Model} not found'];
            }

            $command = new Command({model}: ${model}, isArchived: $archive);
            $response = ($this->handler)($command);

            return [
                'success' => true,
                'uuid' => (string) $response->{model}->uuid,
                'archived' => $response->{model}->isArchived(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
