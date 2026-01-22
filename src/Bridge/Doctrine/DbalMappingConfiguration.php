<?php

namespace Cortex\Bridge\Doctrine;

use Cortex\Component\Mapper\Mapper;

/**
 * DbalMappingConfiguration holds the configuration for mapping between model and database table.
 *
 * @example
 *     $config = new DbalMappingConfiguration(
 *         table: 'my_table',
 *         primaryKey: 'uuid',
 *         modelToTableMapper: new ArrayMapper(
 *             mapping: [
 *                 'my_table_column' => 'myModelProperty',
 *                 'my_table_flattened_column' => fn($modelData) => new JsonString($modelData->myModelStructuredProperty),
 *                 'myModelNonPersistedProperty' => Value::Ignore,
 *             ],
 *             automap: Strategy::AutoMapAll
 *         ),
 *         tableToModelMapper: new CallbackMapper(
 *             fn (array $tableData) => [
 *                 'myModelProperty' => new CustomValueObject($tableData['my_table_column']),
 *                 'myModelStructuredProperty' => new JsonString($tableData['my_table_flattened_column'])->decode(),
 *             ]
 *         )
 *      );
 *
 * @see Cortex\Component\Mapper\ArrayMapper
 * @see Cortex\Component\Mapper\CallbackMapper
 */
class DbalMappingConfiguration
{
    public function __construct(
        public readonly string $table,
        public readonly ?Mapper $modelToTableMapper = null,
        public readonly ?Mapper $tableToModelMapper = null,
        public readonly string $primaryKey = 'uuid',
        public readonly string $modelIdentifier = 'uuid',
        public readonly string $dataChannel = '_default',
        public readonly string $pivotKey = 'uuid',
    ) {
    }
}
