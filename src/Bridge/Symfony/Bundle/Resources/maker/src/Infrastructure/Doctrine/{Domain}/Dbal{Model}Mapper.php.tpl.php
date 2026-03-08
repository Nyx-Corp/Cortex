<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Infrastructure/Doctrine/{Domain}/Dbal{Model}Mapper.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-doctrine.md
 */

namespace Infrastructure\Doctrine\<?php echo $Domain; ?>;

use Cortex\Bridge\Doctrine\DbalBridge;
use Cortex\Bridge\Doctrine\DbalMappingConfiguration;
use Cortex\Bridge\Doctrine\DbalModelAdapterTrait;
use Cortex\Bridge\Symfony\Model\Attribute\Middleware;
use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\Strategy;
use Cortex\Component\Mapper\Value;
use Cortex\Component\Model\ModelMiddleware;
use Cortex\Component\Model\Scope;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;
use Symfony\Component\Uid\Uuid;

#[Middleware(<?php echo $Model; ?>::class, on: Scope::All, handler: 'onDbal', priority: 2)]
class Dbal<?php echo $Model; ?>Mapper implements ModelMiddleware
{
    use DbalModelAdapterTrait;

    public function __construct(
        DbalBridge $dbalBridge,
    ) {
        $this->dbal = $dbalBridge->createAdapter(new DbalMappingConfiguration(
            table: '<?php echo $domain; ?>_<?php echo $table; ?>',
            primaryKey: 'uuid',
            modelToTableMapper: new ArrayMapper([
                // 'column_name' => 'modelProperty',
                // 'modelProperty' => fn($propertyValue, &destKey = 'column_name') => (string) $column_value,
                // 'modelProperty' => Value::Ignore,
            ]),
            tableToModelMapper: new ArrayMapper(
                mapping: [
                    'uuid' => fn (string $uuid) => new Uuid($uuid),
                    // 'column_name' => 'modelProperty',
                ],
                format: Strategy::AutoMapCamel
            ),
        ));
    }
}
