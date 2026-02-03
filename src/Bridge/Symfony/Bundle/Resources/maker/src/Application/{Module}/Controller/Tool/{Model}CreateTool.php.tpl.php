<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Tool/{Model}CreateTool.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?= $Module ?>\Controller\Tool;

use Domain\<?= $Domain ?>\Action\<?= $Model ?>Edit\Command;
use Domain\<?= $Domain ?>\Action\<?= $Model ?>Edit\Handler;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(name: '<?= $tool_name_create ?>', description: 'Create a new <?= $Model ?>')]
class <?= $Model ?>CreateTool
{
    public function __construct(
        private readonly Handler $handler,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        // TODO: Add required fields for creation
    ): array {
        try {
            $command = new Command(
                // TODO: Pass required fields
            );
            $response = ($this->handler)($command);

            return [
                'success' => true,
                'uuid' => (string) $response-><?= $model ?>->uuid,
                'created' => true,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
