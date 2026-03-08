<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Tool/{Model}ArchiveTool.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Tool;

use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Archive\Command;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Archive\Handler;
use Domain\<?php echo $Domain; ?>\Factory\<?php echo $Model; ?>Factory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Symfony\Component\Uid\Uuid;

#[McpTool(name: '<?php echo $tool_name_archive; ?>', description: 'Archive or restore a <?php echo $Model; ?>')]
class <?php echo $Model; ?>ArchiveTool
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
        #[Schema(description: 'UUID of the <?php echo $Model; ?>')]
        string $uuid,
        #[Schema(description: 'Archive (true) or restore (false)')]
        bool $archive = true,
    ): array {
        try {
            $<?php echo $model; ?> = $this->factory->query()
                ->filter(uuid: Uuid::fromString($uuid))
                ->first();

            if (null === $<?php echo $model; ?>) {
                return ['success' => false, 'error' => '<?php echo $Model; ?> not found'];
            }

            $command = new Command(<?php echo $model; ?>: $<?php echo $model; ?>, isArchived: $archive);
            $response = ($this->handler)($command);

            return [
                'success' => true,
                'uuid' => (string) $response-><?php echo $model; ?>->uuid,
                'archived' => $response-><?php echo $model; ?>->isArchived(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
