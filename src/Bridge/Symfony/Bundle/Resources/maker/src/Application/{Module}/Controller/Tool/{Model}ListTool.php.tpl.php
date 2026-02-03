<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Tool/{Model}ListTool.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?= $Module ?>\Controller\Tool;

use Cortex\Component\Model\Query\Pager;
use Domain\<?= $Domain ?>\Factory\<?= $Model ?>Factory;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(name: '<?= $tool_name_list ?>', description: 'List <?= $Model ?> with optional filters')]
class <?= $Model ?>ListTool
{
    public function __construct(
        private readonly <?= $Model ?>Factory $factory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        #[Schema(description: 'Page number (1-indexed)')]
        int $page = 1,
        #[Schema(description: 'Items per page (max 100)')]
        int $limit = 20,
        #[Schema(description: 'Include archived items')]
        bool $includeArchived = false,
        // TODO: Add model-specific filters
    ): array {
        try {
            $query = $this->factory->query();

            if (!$includeArchived) {
                $query->filter(archivedAt: null);
            }

            $query->paginate(new Pager($page, min($limit, 100)));
            $collection = $query->all();

            return [
                'success' => true,
                'items' => array_map(fn ($<?= $model ?>) => [
                    'uuid' => (string) $<?= $model ?>->uuid,
                    // TODO: Add model fields
                ], $collection->toArray()),
                'paging' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $query->pager->nbRecords ?? count($collection),
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
