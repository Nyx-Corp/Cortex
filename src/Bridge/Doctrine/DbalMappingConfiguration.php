<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

use Cortex\Component\Mapper\Mapper;

/**
 * DbalMappingConfiguration holds the configuration for mapping between model and database table.
 *
 * @example
 *     $config = new DbalMappingConfiguration(
 *         table: 'my_table',
 *         primaryKey: 'uuid',
 *         joins: [
 *             'relation' => new JoinDefinition(
 *                 table: 'related_table',
 *                 on: 'my_table.relation_uuid = related_table.uuid',
 *                 type: JoinType::Inner,
 *                 alias: 'rel',
 *                 fields: ['name', 'type'],
 *             ),
 *         ],
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
 * @see JoinDefinition
 */
class DbalMappingConfiguration
{
    /**
     * @param string                        $table              Main table name
     * @param class-string|null             $modelClass         Model class for preloader lookup (e.g., Club::class)
     * @param array<string, JoinDefinition> $joins              JOIN definitions keyed by relation name
     * @param Mapper|null                   $modelToTableMapper Mapper for model to table conversion
     * @param Mapper|null                   $tableToModelMapper Mapper for table to model conversion
     * @param string                        $primaryKey         Primary key column name
     * @param string                        $modelIdentifier    Model identifier property name
     * @param string                        $dataChannel        Data channel name for middleware
     * @param string                        $pivotKey           Pivot key for result grouping
     */
    public function __construct(
        public readonly string $table,
        public readonly ?string $modelClass = null,
        public readonly array $joins = [],
        public readonly ?Mapper $modelToTableMapper = null,
        public readonly ?Mapper $tableToModelMapper = null,
        public readonly string $primaryKey = 'uuid',
        public readonly string $modelIdentifier = 'uuid',
        public readonly string $dataChannel = '_default',
        public readonly string $pivotKey = 'uuid',
    ) {
    }

    /**
     * Build SQL JOIN clauses from all join definitions.
     */
    public function buildJoinClauses(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        return ' ' . implode(' ', array_map(
            fn(JoinDefinition $join) => $join->toSql($this->table),
            $this->joins
        ));
    }

    /**
     * Build SELECT fields from all join definitions.
     *
     * Uses unique alias prefix for each join to avoid column collisions.
     */
    public function buildJoinSelectFields(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $fields = array_filter(array_map(
            fn(JoinDefinition $join) => $join->getSelectFields(),
            $this->joins
        ));

        return empty($fields) ? '' : implode(', ', $fields);
    }

    /**
     * Check if a filter key belongs to a join.
     *
     * Supports both dot notation (relation.field) and underscore notation (relation_field).
     * Underscore notation is useful for form field names which don't allow dots.
     *
     * @param string $filterKey The filter key to check
     * @return array{join: JoinDefinition, field: string}|null The join and field if found
     */
    public function resolveJoinFilter(string $filterKey): ?array
    {
        // Check for dot notation: "relation.field"
        if (str_contains($filterKey, '.')) {
            [$relationName, $field] = explode('.', $filterKey, 2);
            if (isset($this->joins[$relationName])) {
                return [
                    'join' => $this->joins[$relationName],
                    'field' => $field,
                ];
            }
        }

        // Check for underscore notation: "relation_field" (for form compatibility)
        foreach ($this->joins as $relationName => $join) {
            $prefix = $relationName . '_';
            if (str_starts_with($filterKey, $prefix)) {
                $field = substr($filterKey, strlen($prefix));
                return [
                    'join' => $join,
                    'field' => $field,
                ];
            }
        }

        return null;
    }

    /**
     * Resolve a unique field to a qualified SQL column for GROUP BY.
     *
     * Maps model field name (e.g., 'contact') to qualified column (e.g., 'contact_role.contact_uuid').
     *
     * @param string $field Model field name
     * @return string Qualified SQL column name
     */
    public function resolveUniqueField(string $field): string
    {
        // Try to map via modelToTableMapper
        if ($this->modelToTableMapper) {
            $mapped = $this->modelToTableMapper->map([$field => '']);
            $column = array_key_first($mapped);

            if ($column && $column !== $field) {
                // Qualify with table name
                return $this->table . '.' . $column;
            }
        }

        // Fallback: use field as-is, qualified with table name
        return $this->table . '.' . $field;
    }
}
