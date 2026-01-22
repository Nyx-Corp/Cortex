<?php

namespace Infrastructure\Doctrine\{Domain};

use Cortex\Bridge\Doctrine\DbalBridge;
use Cortex\Bridge\Doctrine\DbalMappingConfiguration;
use Cortex\Bridge\Doctrine\DbalModelAdapterTrait;
use Cortex\Bridge\Symfony\Model\Attribute\Middleware;
use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\Strategy;
use Cortex\Component\Mapper\Value;
use Cortex\Component\Model\ModelMiddleware;
use Cortex\Component\Model\Scope;
use Domain\{Domain}\Model\{Model};
use Symfony\Component\Uid\Uuid;

#[Middleware({Model}::class, on: Scope::All, handler: 'onDbal', priority: 2)]
class Dbal{Model}Mapper implements ModelMiddleware
{
    use DbalModelAdapterTrait;

    public function __construct(
        DbalBridge $dbalBridge,
    ) {
        $this->dbal = $dbalBridge->createAdapter(new DbalMappingConfiguration(
            table: '{domain}_{table}',
            primaryKey: 'uuid',
            modelToTableMapper: new ArrayMapper([
                // 'column_name' => 'modelProperty',
                // 'modelProperty' => fn($propertyValue, &destKey = 'column_name') => (string) $column_value,
                // 'modelProperty' => Value::Ignore,
            ]),
            tableToModelMapper:  new ArrayMapper(
                mapping: [
                    'uuid' => fn (string $uuid) => new Uuid($uuid),
                    // 'column_name' => 'modelProperty',
                ],
                format: Strategy::AutoMapCamel
            ),
        ));
    }

    
}
