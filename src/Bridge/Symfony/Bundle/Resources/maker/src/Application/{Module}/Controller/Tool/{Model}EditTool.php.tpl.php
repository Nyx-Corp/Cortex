<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Tool/{Model}EditTool.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Tool;

use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Edit\Command;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Edit\Handler;
use Domain\<?php echo $Domain; ?>\Factory\<?php echo $Model; ?>Factory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\Uid\Uuid;

#[McpTool(name: '<?php echo $tool_name_edit; ?>', description: 'Update an existing <?php echo $Model; ?>')]
class <?php echo $Model; ?>EditTool
{
    public function __construct(
        private readonly Handler $handler,
        private readonly <?php echo $Model; ?>Factory $factory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        #[Schema(description: 'UUID of the <?php echo $Model; ?> to update')]
        string $uuid,
        // TODO: Add optional fields for update
    ): array {
        try {
            $<?php echo $model; ?> = $this->factory->query()
                ->filter(uuid: Uuid::fromString($uuid))
                ->first();

            if (null === $<?php echo $model; ?>) {
                return ['success' => false, 'error' => '<?php echo $Model; ?> not found'];
            }

            $command = new Command(
                uuid: Uuid::fromString($uuid),
                // TODO: Pass fields
            );
            $response = ($this->handler)($command);

            return [
                'success' => true,
                'uuid' => (string) $response-><?php echo $model; ?>->uuid,
                'updated' => true,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
