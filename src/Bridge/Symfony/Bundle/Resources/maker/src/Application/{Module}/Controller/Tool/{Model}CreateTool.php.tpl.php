<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Tool/{Model}CreateTool.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Tool;

use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Edit\Command;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Edit\Handler;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(name: '<?php echo $tool_name_create; ?>', description: 'Create a new <?php echo $Model; ?>')]
class <?php echo $Model; ?>CreateTool
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
                'uuid' => (string) $response-><?php echo $model; ?>->uuid,
                'created' => true,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
