<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

use Cortex\Component\Model\Factory\ModelFactory;

use function Symfony\Component\String\u;

/**
 * Defines a JOIN relationship using the joined model's DBAL configuration.
 *
 * Columns are automatically discovered from the factory's model prototype,
 * with convention-based FK detection for relation properties.
 *
 * Convention: FK = {relation}_uuid
 *
 * @example
 *     // Minimal: uses convention {relation}_uuid for localKey
 *     new JoinDefinition(
 *         factory: $this->organisationFactory,
 *         joinConfig: $this->organisationMapper->getConfiguration(),
 *     )
 *
 *     // With explicit localKey (overrides convention)
 *     new JoinDefinition(
 *         factory: $this->organisationFactory,
 *         joinConfig: $this->organisationMapper->getConfiguration(),
 *         localKey: 'custom_org_uuid',
 *     )
 */
final class JoinDefinition
{
    private readonly string $alias;

    /** @var array<string> Column names in snake_case */
    private readonly array $columns;

    /**
     * @param ModelFactory             $factory         Factory to resolve the joined model (provides column discovery)
     * @param DbalMappingConfiguration $joinConfig      DBAL config of the joined model
     * @param string|null              $localKey        Foreign key column in the main table (defaults to {relation}_uuid)
     * @param JoinType                 $type            Type of join (INNER, LEFT, RIGHT)
     * @param string|null              $alias           Table alias (auto-generated as c1, c2... if null)
     * @param array<string, string>    $columnOverrides Override auto-discovered column names (model_prop => column_name)
     * @param string|null              $parentAlias     Parent alias for nested joins (used internally)
     * @param string|null              $relationName    Relation name for convention-based FK (injected by DbalMappingConfiguration)
     */
    public function __construct(
        public readonly ModelFactory $factory,
        public readonly DbalMappingConfiguration $joinConfig,
        public readonly ?string $localKey = null,
        public readonly JoinType $type = JoinType::Inner,
        ?string $alias = null,
        private readonly array $columnOverrides = [],
        private readonly ?string $parentAlias = null,
        private readonly ?string $relationName = null,
    ) {
        // Generate stable alias using static counter
        static $counter = 0;
        $baseAlias = $alias ?? ('c'.++$counter);
        $this->alias = $parentAlias ? "{$parentAlias}_{$baseAlias}" : $baseAlias;

        // Discover columns from factory's model prototype constructor parameters
        // Convert camelCase keys to snake_case for SQL
        // Convention: if property is a class type (relation), add _uuid suffix
        $modelClass = (string) $factory->modelPrototype->modelClass;
        $parameterTypes = $this->getConstructorParameterTypes($modelClass);

        $this->columns = array_map(
            function (string $key) use ($parameterTypes): string {
                // Explicit override takes precedence
                if (isset($this->columnOverrides[$key])) {
                    return $this->columnOverrides[$key];
                }

                $snakeKey = u($key)->snake()->toString();

                // If this property is a relation (class type or in joinConfig.joins), use FK convention
                if (isset($this->joinConfig->joins[$key])) {
                    return $snakeKey.'_uuid';
                }

                // Check if the property type is a class (indicates a relation)
                if (isset($parameterTypes[$key]) && $parameterTypes[$key]['isClass']) {
                    return $snakeKey.'_uuid';
                }

                return $snakeKey;
            },
            $factory->modelPrototype->constructors->declaredKeys()
        );
    }

    /**
     * Get the effective local key, using convention if not explicitly set.
     *
     * Convention: {relationName}_uuid
     */
    public function getLocalKey(): string
    {
        return $this->localKey ?? ($this->relationName.'_uuid');
    }

    /**
     * Create a copy with the relation name set.
     *
     * Used by DbalMappingConfiguration to inject the relation name for convention-based FK.
     */
    public function withRelationName(string $name): self
    {
        return new self(
            factory: $this->factory,
            joinConfig: $this->joinConfig,
            localKey: $this->localKey,
            type: $this->type,
            alias: $this->alias,
            columnOverrides: $this->columnOverrides,
            parentAlias: $this->parentAlias,
            relationName: $name,
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
            $this->getLocalKey(),
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
            $parts[] = sprintf(
                '%s.%s AS %s_%s',
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
     *
     * @return array|null Clean data keyed by column names, or null if primary key not found
     */
    public function extractJoinedData(array $row): ?array
    {
        $prefix = $this->alias.'_';
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
        $prefix = $this->alias.'_';

        return array_map(
            static fn (string $col) => $prefix.$col,
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

    /**
     * Create a copy of this join with a parent alias for hierarchical nesting.
     *
     * Used internally by DbalMappingConfiguration::buildJoinClausesRecursive()
     * to create properly aliased nested joins.
     */
    public function withParentAlias(string $parentAlias): self
    {
        // Get base alias: if we already have a parent, strip it; otherwise use our alias directly
        $baseAlias = $this->alias;
        if (null !== $this->parentAlias) {
            // Current alias is "parent_base", extract base
            $prefix = $this->parentAlias.'_';
            if (str_starts_with($this->alias, $prefix)) {
                $baseAlias = substr($this->alias, strlen($prefix));
            }
        }

        return new self(
            factory: $this->factory,
            joinConfig: $this->joinConfig,
            localKey: $this->localKey,
            type: $this->type,
            alias: $baseAlias, // preserve base alias, constructor will add new parent prefix
            columnOverrides: $this->columnOverrides,
            parentAlias: $parentAlias,
            relationName: $this->relationName,
        );
    }

    /**
     * Get constructor parameter types using reflection.
     *
     * Returns an array keyed by parameter name with type info.
     * Only model classes are marked as relations (excludes value objects, enums, dates).
     *
     * @param class-string $modelClass
     *
     * @return array<string, array{isClass: bool, type: string|null}>
     */
    private function getConstructorParameterTypes(string $modelClass): array
    {
        $types = [];

        try {
            $refClass = new \ReflectionClass($modelClass);
            $constructor = $refClass->getConstructor();

            if (null === $constructor) {
                return $types;
            }

            foreach ($constructor->getParameters() as $param) {
                $paramType = $param->getType();
                $isRelation = false;
                $typeName = null;

                if ($paramType instanceof \ReflectionNamedType) {
                    $typeName = $paramType->getName();
                    // Check if this is likely a model class (relation)
                    // Exclude: builtin types, enums, value objects, dates
                    $isRelation = $this->isModelType($typeName);
                }

                $types[$param->getName()] = [
                    'isClass' => $isRelation,
                    'type' => $typeName,
                ];
            }
        } catch (\ReflectionException) {
            // Ignore reflection errors
        }

        return $types;
    }

    /**
     * Check if a type name represents a model class (as opposed to a value object).
     *
     * Model classes are relations that should be stored as FK (uuid).
     * Value objects (Uuid, DateTime, Email, enums) are stored directly.
     */
    private function isModelType(string $typeName): bool
    {
        // Builtin types are not models
        if (in_array($typeName, ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'null', 'callable', 'iterable', 'void', 'never', 'true', 'false'], true)) {
            return false;
        }

        // Enums are not models
        if (enum_exists($typeName)) {
            return false;
        }

        // Check if class exists before checking interfaces
        if (!class_exists($typeName) && !interface_exists($typeName)) {
            return false;
        }

        // DateTime types are not models
        if (is_a($typeName, \DateTimeInterface::class, true)) {
            return false;
        }

        // Symfony Uid types (Uuid, Ulid) are not models
        if (is_a($typeName, \Symfony\Component\Uid\AbstractUid::class, true)) {
            return false;
        }

        // Cortex value objects are not models
        if (is_a($typeName, \Cortex\ValueObject\ValueObject::class, true)) {
            return false;
        }

        // Stringable interface is not a model
        if (\Stringable::class === $typeName) {
            return false;
        }

        // Otherwise, assume it's a model class (relation)
        return true;
    }
}
