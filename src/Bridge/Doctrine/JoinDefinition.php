<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

use Cortex\Component\Model\Factory\ModelFactory;

use function Symfony\Component\String\u;

/**
 * Defines a JOIN relationship using the joined model's DBAL configuration.
 *
 * Columns are automatically discovered from the factory's model prototype,
 * with optional overrides for non-standard mappings.
 *
 * @example
 *     // Minimal: auto-discover columns
 *     new JoinDefinition(
 *         factory: $this->organisationFactory,
 *         joinConfig: $this->organisationMapper->getConfiguration(),
 *         localKey: 'organisation_uuid',
 *     )
 *
 *     // With column overrides for non-standard mappings (e.g., parent → parent_uuid)
 *     new JoinDefinition(
 *         factory: $this->organisationFactory,
 *         joinConfig: $this->organisationMapper->getConfiguration(),
 *         localKey: 'organisation_uuid',
 *         columnOverrides: ['parent' => 'parent_uuid'],
 *     )
 */
final readonly class JoinDefinition
{
    private string $alias;

    /** @var array<string> Column names in snake_case */
    private array $columns;

    /**
     * @param ModelFactory              $factory         Factory to resolve the joined model (provides column discovery)
     * @param DbalMappingConfiguration  $joinConfig      DBAL config of the joined model
     * @param string                    $localKey        Foreign key column in the main table
     * @param JoinType                  $type            Type of join (INNER, LEFT, RIGHT)
     * @param string|null               $alias           Table alias (auto-generated as c1, c2... if null)
     * @param array<string, string>     $columnOverrides Override auto-discovered column names (model_prop => column_name)
     */
    public function __construct(
        public ModelFactory $factory,
        public DbalMappingConfiguration $joinConfig,
        public string $localKey,
        public JoinType $type = JoinType::Inner,
        ?string $alias = null,
        array $columnOverrides = [],
    ) {
        // Generate stable alias using static counter
        static $counter = 0;
        $this->alias = $alias ?? ('c' . ++$counter);

        // Discover columns from factory's model prototype constructor parameters
        // Convert camelCase keys to snake_case for SQL, with optional overrides
        $this->columns = array_map(
            static fn(string $key) => $columnOverrides[$key]
                ?? u($key)->snake()->toString(),
            $factory->modelPrototype->constructors->declaredKeys()
        );
    }

    /**
     * Get the effective alias for this join.
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Get the joined table name.
     */
    public function getTable(): string
    {
        return $this->joinConfig->table;
    }

    /**
     * Get the primary key of the joined table.
     */
    public function getForeignKey(): string
    {
        return $this->joinConfig->primaryKey;
    }

    /**
     * Get the model class of the joined model.
     */
    public function getModelClass(): ?string
    {
        return $this->joinConfig->modelClass;
    }

    /**
     * Get the discovered columns.
     *
     * @return array<string> Column names in snake_case
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Build the SQL JOIN clause.
     */
    public function toSql(string $mainTable): string
    {
        return sprintf(
            '%s %s AS %s ON %s.%s = %s.%s',
            $this->type->value,
            $this->joinConfig->table,
            $this->alias,
            $mainTable,
            $this->localKey,
            $this->alias,
            $this->joinConfig->primaryKey
        );
    }

    /**
     * Build the SELECT fields for this join.
     *
     * Uses unique prefix (alias_column) to avoid column name collisions.
     */
    public function getSelectFields(): string
    {
        $parts = [];
        foreach ($this->columns as $column) {
            // alias.column AS alias_column
            $parts[] = sprintf('%s.%s AS %s_%s',
                $this->alias,
                $column,
                $this->alias,
                $column
            );
        }

        return implode(', ', $parts);
    }

    /**
     * Extract joined data from a result row.
     *
     * Removes prefixed columns and returns clean data keyed by original column names.
     *
     * @param array $row The result row containing prefixed columns
     * @return array|null Clean data keyed by column names, or null if primary key not found
     */
    public function extractJoinedData(array $row): ?array
    {
        $prefix = $this->alias . '_';
        $prefixLen = strlen($prefix);
        $joinedData = [];

        foreach ($row as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $originalKey = substr($key, $prefixLen);
                $joinedData[$originalKey] = $value;
            }
        }

        // Verify we have the primary key
        if (!isset($joinedData[$this->joinConfig->primaryKey])) {
            return null;
        }

        return $joinedData;
    }

    /**
     * Get prefixed column names for stripping from result rows.
     *
     * @return array<string> Prefixed column names (alias_column)
     */
    public function getPrefixedColumns(): array
    {
        $prefix = $this->alias . '_';
        return array_map(
            static fn(string $col) => $prefix . $col,
            $this->columns
        );
    }

    /**
     * Check if this join has explicit columns (always true with factory-based discovery).
     */
    public function hasExplicitFields(): bool
    {
        return true;
    }

    /**
     * Get field mapping: source column => result alias.
     *
     * @return array<string, string>
     */
    public function getFieldMapping(): array
    {
        $mapping = [];
        foreach ($this->columns as $column) {
            $mapping[$column] = sprintf('%s_%s', $this->alias, $column);
        }
        return $mapping;
    }
}
