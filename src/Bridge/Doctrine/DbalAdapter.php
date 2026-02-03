<?php

declare(strict_types=1);

namespace Cortex\Bridge\Doctrine;

use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Json\JsonString;
use Cortex\Component\Middleware\Middleware;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\Component\Model\Query\Operator;
use Cortex\Component\Model\Store\SyncCommand;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class DbalAdapter
{
    private static string $operatorFilterPattern;

    public function __construct(
        protected Connection $dbalConnection,
        private DbalMappingConfiguration $configuration,
        private ?DbalPreloader $preloader = null,
    ) {
        self::$operatorFilterPattern ??= '/^('.Operator::pattern().')/';
    }

    /**
     * Get the configuration.
     */
    public function getConfiguration(): DbalMappingConfiguration
    {
        return $this->configuration;
    }

    public function query(string $query, array $params = []): \Generator
    {
        // Expand LIKE pattern arrays into individual parameters
        $params = $this->expandLikeParams($params);

        $stmt = $this->dbalConnection->executeQuery(
            $query,
            array_map(
                static fn ($param) => match (true) {
                    null === $param => null,
                    $param instanceof \BackedEnum => $param->value,
                    is_string($param) => preg_filter(self::$operatorFilterPattern, '', $param) ?? $param,
                    is_scalar($param) => $param,
                    is_array($param) => $param,
                    default => (string) $param,
                },
                $params
            ),
            array_map(
                static fn ($param) => match (true) {
                    null === $param => ParameterType::NULL,
                    is_array($param) => ArrayParameterType::STRING,
                    default => ParameterType::STRING,
                },
                $params
            )
        );

        while (($row = $stmt->fetchAssociative()) !== false) {
            yield $row;
        }

        $stmt->free();
    }

    /**
     * Expand arrays of LIKE patterns into individual named parameters.
     * ['roles' => ['~%"admin"%', '~%"member"%']] becomes ['roles_0' => '%"admin"%', 'roles_1' => '%"member"%'].
     */
    private function expandLikeParams(array $params): array
    {
        $expanded = [];

        foreach ($params as $key => $value) {
            if (!is_array($value)) {
                $expanded[$key] = $value;
                continue;
            }

            // Check if all values are LIKE patterns
            $allLikePatterns = count($value) > 0 && array_reduce(
                $value,
                fn (bool $carry, $v) => $carry && is_string($v) && str_starts_with($v, Operator::Like->value),
                true
            );

            if ($allLikePatterns) {
                // Expand to individual parameters with ~ prefix stripped
                foreach ($value as $i => $pattern) {
                    $expanded[$key.'_'.$i] = $pattern;
                }
            } else {
                $expanded[$key] = $value;
            }
        }

        return $expanded;
    }

    public function where(array $params): string
    {
        $hasJoins = !empty($this->configuration->joins);

        return count($params) ? ' WHERE '.implode(' AND ', array_map(
            function ($key, $value) use ($hasJoins): string {
                // Qualify column with main table if joins exist and column is not already qualified
                $column = $key;
                if ($hasJoins && !str_contains($key, '.')) {
                    $column = $this->configuration->table.'.'.$key;
                }

                // Use unqualified key for parameter binding
                $paramKey = str_replace('.', '_', $key);

                if (is_array($value)) {
                    // Check if all values are LIKE patterns (start with ~)
                    $allLikePatterns = count($value) > 0 && array_reduce(
                        $value,
                        fn (bool $carry, $v) => $carry && is_string($v) && str_starts_with($v, Operator::Like->value),
                        true
                    );

                    if ($allLikePatterns) {
                        // Generate OR clause for LIKE patterns: (col LIKE :key_0 OR col LIKE :key_1)
                        $likeClauses = array_map(
                            fn (int $i) => sprintf('%s LIKE :%s_%d', $column, $paramKey, $i),
                            array_keys($value)
                        );

                        return '('.implode(' OR ', $likeClauses).')';
                    }

                    return sprintf('%s IN (:%s)', $column, $paramKey);
                }
                if (is_null($value)) {
                    return sprintf('%s IS NULL', $column);
                }

                // Handle BackedEnum - use its value for operator detection
                $stringValue = $value instanceof \BackedEnum ? (string) $value->value : $value;

                $operator = is_string($stringValue) ? array_find(
                    Operator::cases(),
                    static fn (Operator $op) => str_starts_with($stringValue, $op->value)
                ) : null;
                $operator ??= Operator::Equal;

                return sprintf('%s %s :%s', $column, $operator->toSql(), $paramKey);
            },
            array_keys($params),
            array_values($params)
        )) : '';
    }

    public function select(
        array $params = [],
        string $fields = '*',
        int $limit = 0,
        ?int $offset = null,
        ?string $sortBy = null,
        string $sortDirection = 'asc',
        ?string $groupBy = null,
    ): string {
        // Build JOIN clauses from configuration
        $joinClauses = $this->configuration->buildJoinClauses();
        $joinSelectFields = $this->configuration->buildJoinSelectFields();

        // Enhance fields with join fields when selecting all
        if ('*' === $fields && '' !== $joinSelectFields) {
            $fields = $this->configuration->table.'.*, '.$joinSelectFields;
        }

        // Qualify sortBy with table name if it's not already qualified and joins exist
        if ($sortBy && !empty($this->configuration->joins) && !str_contains($sortBy, '.')) {
            $sortBy = $this->configuration->table.'.'.$sortBy;
        }

        // When groupBy is specified, use subquery to get unique primary keys first
        // This is compatible with MySQL ONLY_FULL_GROUP_BY mode
        if ($groupBy) {
            $subquery = sprintf(
                'SELECT MIN(%s.%s) FROM %s%s%s GROUP BY %s',
                $this->configuration->table,
                $this->configuration->pivotKey,
                $this->configuration->table,
                $joinClauses,
                $this->where($params),
                $groupBy
            );

            return trim(sprintf(
                'SELECT %s FROM %s%s WHERE %s.%s IN (%s)%s%s%s',
                $fields,
                $this->configuration->table,
                $joinClauses,
                $this->configuration->table,
                $this->configuration->pivotKey,
                $subquery,
                $sortBy ? ' ORDER BY '.$sortBy.' '.strtoupper($sortDirection) : '',
                $limit > 0 ? ' LIMIT '.$limit : '',
                $limit > 0 && null !== $offset ? ' OFFSET '.$offset : '',
            ));
        }

        return trim(sprintf(
            'SELECT %s FROM %s%s%s%s%s%s',
            $fields,
            $this->configuration->table,
            $joinClauses,
            $this->where($params),
            $sortBy ? ' ORDER BY '.$sortBy.' '.strtoupper($sortDirection) : '',
            $limit > 0 ? ' LIMIT '.$limit : '',
            $limit > 0 && null !== $offset ? ' OFFSET '.$offset : '',
        ));
    }

    public function insert(array $data): string
    {
        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->configuration->table,
            implode(', ', array_keys($data)),
            implode(', ', array_map(fn ($key) => ':'.$key, array_keys($data)))
        );
    }

    public function update(array $data): string
    {
        return sprintf(
            'UPDATE %s SET %s WHERE %s=:%s',
            $this->configuration->table,
            implode(', ', array_map(fn ($key) => sprintf('%s=:%s', $key, $key), array_keys($data))),
            $this->configuration->primaryKey,
            $this->configuration->primaryKey
        );
    }

    public function sync(array $data): string
    {
        return trim(sprintf(
            'INSERT INTO %s (%s) VALUES (%s) %s',
            $this->configuration->table,
            implode(', ', array_keys($data)),
            implode(', ', array_map(fn ($key) => ':'.$key, array_keys($data))),
            count($data) <= 1 ? '' : sprintf(
                'ON DUPLICATE KEY UPDATE %s',
                implode(', ', array_map(
                    fn ($key) => sprintf('%s=:%s', $key, $key),
                    array_filter(
                        array_keys($data),      // don't update primary key
                        fn (string $key): bool => $key !== $this->configuration->primaryKey
                    )
                ))
            )
        ));
    }

    public function delete(): string
    {
        return sprintf(
            'DELETE FROM %s WHERE %s=:%s',
            $this->configuration->table,
            $this->configuration->primaryKey,
            $this->configuration->primaryKey
        );
    }

    /**
     * Build a COUNT query, handling groupBy for unique counts.
     */
    public function count(array $params = [], ?string $groupBy = null): string
    {
        $joinClauses = $this->configuration->buildJoinClauses();

        if ($groupBy) {
            // Count distinct grouped values
            return trim(sprintf(
                'SELECT COUNT(DISTINCT %s) AS count FROM %s%s%s',
                $groupBy,
                $this->configuration->table,
                $joinClauses,
                $this->where($params)
            ));
        }

        return trim(sprintf(
            'SELECT COUNT(*) AS count FROM %s%s%s',
            $this->configuration->table,
            $joinClauses,
            $this->where($params)
        ));
    }

    public function onModelQuery(Middleware $chain, ModelQuery $query): \Generator
    {
        // Check preloader for single-UUID lookup
        $uuidFilter = $query->filters->get('uuid');
        if ($uuidFilter && $this->preloader && $this->configuration->modelClass) {
            if ($this->preloader->has($this->configuration->modelClass, $uuidFilter)) {
                $cachedData = $this->preloader->get($this->configuration->modelClass, $uuidFilter);
                $mappedData = $this->configuration->tableToModelMapper?->map($cachedData) ?? $cachedData;

                // Auto-hydrate relations if enabled
                if ($this->configuration->autoHydrate) {
                    $mappedData = $this->hydrateRelations($mappedData);
                }

                yield $uuidFilter => [
                    $this->configuration->dataChannel => $mappedData,
                ];

                // Consume-once: remove after use
                $this->preloader->remove($this->configuration->modelClass, $uuidFilter);

                return;
            }
        }

        $filters = $this->configuration->modelToTableMapper?->map($query->filters->all())
            ?? $query->filters->all()
        ;

        // Process filters for join-qualified fields (e.g., "organisation.name" -> "org.name")
        $filters = $this->resolveJoinFilters($filters);

        // Normalize filter keys for parameter binding (dots to underscores)
        $queryParams = $this->normalizeParamKeys($filters);

        $limit = $query->limit ?? 0;

        // Resolve uniqueField to GROUP BY clause (needed for both count and select)
        $groupBy = null;
        if ($query->uniqueField) {
            $groupBy = $this->configuration->resolveUniqueField($query->uniqueField);
        }

        if ($query->pager && !$limit) {
            $count = iterator_to_array($this->query($this->count($filters, $groupBy), $queryParams))[0]['count'] ?? 0;
            $query->pager->bind($count);

            if (0 === $count) {
                yield from [];

                return; // no results
            }
        }

        $sortBy = null;
        if ($query->sorter) {
            // Convert field name from model (camelCase) to table (snake_case)
            $sortBy = array_key_first(
                $this->configuration->modelToTableMapper?->map([
                    $query->sorter->field => '', // Use empty string as placeholder
                ])
            );

            // Check if sortBy targets a joined table
            $joinInfo = $this->configuration->resolveJoinFilter($query->sorter->field);
            if ($joinInfo) {
                $sortBy = $joinInfo['join']->getAlias().'.'.$this->toSnakeCase($joinInfo['field']);
            }
        }

        $results = AsyncCollection::create(
            $this->query(
                $this->select(
                    $filters,
                    limit: $limit ?: $query->pager?->nbPerPage ?? 0,
                    offset: $query->pager?->offset ?? null,
                    sortBy: $sortBy ?? null,
                    sortDirection: $query->sorter?->direction->value ?? 'asc',
                    groupBy: $groupBy,
                ),
                $queryParams
            )
        );

        // last middleware in the chain => yield directly
        if ($chain->isLast) {
            foreach ($results as $dataLine) {
                $identifierValue = $dataLine[$this->configuration->pivotKey] ?? null;
                if (null === $identifierValue) {
                    throw new \InvalidArgumentException(sprintf('Identifier "%s" not found in data line: %s', $this->configuration->pivotKey, new JsonString($dataLine)));
                }

                // Preload joined data for related mappers and clean up joined columns
                $this->preloadJoinedData($dataLine);
                $cleanedDataLine = $this->stripJoinedColumns($dataLine);

                $mappedData = $this->configuration->tableToModelMapper->map($cleanedDataLine);

                // Auto-hydrate relations if enabled
                if ($this->configuration->autoHydrate) {
                    $mappedData = $this->hydrateRelations($mappedData);
                }

                yield $identifierValue => [
                    $this->configuration->dataChannel => $mappedData,
                ];
            }

            return;
        }

        // try to map results to next middleware
        foreach ($chain->next() as $identifier => $dataLine) {
            // find the identifier in results
            $resultLine = $results->find(static fn ($line) => $line[$this->configuration->pivotKey] === $identifier);
            if (null === $resultLine) {
                // log ?
                yield $identifier => $dataLine; // pass through unchanged
                continue;
            }

            // Preload joined data for related mappers and clean up joined columns
            $this->preloadJoinedData($resultLine);
            $cleanedResultLine = $this->stripJoinedColumns($resultLine);

            $mappedData = $this->configuration->tableToModelMapper->map($cleanedResultLine);

            // Auto-hydrate relations if enabled
            if ($this->configuration->autoHydrate) {
                $mappedData = $this->hydrateRelations($mappedData);
            }

            yield $identifier => array_replace_recursive(
                $dataLine,
                [
                    '_dbal' => $resultLine,
                    $this->configuration->dataChannel => $mappedData,
                ],
            );
        }
    }

    /**
     * Preload joined data into the preloader for consumption by related mappers.
     *
     * For each join, extracts prefixed columns and stores them in the preloader
     * so the related mapper can use them instead of executing a separate query.
     * Supports nested JOINs up to the configured joinDepth.
     *
     * @param array                             $dataLine The data row containing prefixed columns
     * @param array<string,JoinDefinition>|null $joins    Joins to process (null = use configuration)
     * @param int                               $depth    Current recursion depth
     * @param array                             $visited  Visited model classes to prevent circular refs
     */
    private function preloadJoinedData(
        array $dataLine,
        ?array $joins = null,
        int $depth = 1,
        array $visited = []
    ): void {
        if (!$this->preloader) {
            return;
        }

        $joins ??= $this->configuration->joins;

        foreach ($joins as $join) {
            $modelClass = $join->getModelClass();

            // Prevent circular references
            if ($modelClass && in_array($modelClass, $visited, true)) {
                continue;
            }

            if (!$modelClass) {
                continue;
            }

            // Use JoinDefinition's extractJoinedData method
            $joinedData = $join->extractJoinedData($dataLine);
            if (null === $joinedData) {
                continue;
            }

            // Get identifier from joined data (primary key)
            $identifier = $joinedData[$join->getForeignKey()] ?? null;
            if (null === $identifier) {
                continue;
            }

            // Preload data for the related mapper
            $this->preloader->set($modelClass, $identifier, $joinedData);

            // Recursively preload nested joins if within depth limit
            $nestedJoins = $join->joinConfig->joins;
            if ($depth < $this->configuration->joinDepth && !empty($nestedJoins)) {
                // Create nested joins with proper aliases
                $nestedWithAliases = [];
                foreach ($nestedJoins as $name => $nestedJoin) {
                    $nestedWithAliases[$name] = $nestedJoin->withParentAlias($join->getAlias());
                }

                $visitedWithCurrent = $visited;
                $visitedWithCurrent[] = $modelClass;

                $this->preloadJoinedData(
                    $dataLine,
                    $nestedWithAliases,
                    $depth + 1,
                    $visitedWithCurrent
                );
            }
        }
    }

    /**
     * Remove joined columns from data line before passing to tableToModelMapper.
     *
     * Keeps the local key (foreign key column) as it's needed by the tableToModelMapper
     * to convert it to the relation property name.
     * Supports nested JOINs up to the configured joinDepth.
     *
     * @param array                             $dataLine The data row containing prefixed columns
     * @param array<string,JoinDefinition>|null $joins    Joins to process (null = use configuration)
     * @param int                               $depth    Current recursion depth
     * @param array                             $visited  Visited model classes to prevent circular refs
     */
    private function stripJoinedColumns(
        array $dataLine,
        ?array $joins = null,
        int $depth = 1,
        array $visited = []
    ): array {
        $joins ??= $this->configuration->joins;

        foreach ($joins as $join) {
            $modelClass = $join->getModelClass();

            // Prevent circular references
            if ($modelClass && in_array($modelClass, $visited, true)) {
                continue;
            }

            // Remove all prefixed columns (alias_column)
            foreach ($join->getPrefixedColumns() as $prefixedColumn) {
                unset($dataLine[$prefixedColumn]);
            }
            // Keep the local key (e.g., organisation_uuid) - the tableToModelMapper needs it
            // to map it to the relation property name (e.g., organisation)

            // Recursively strip nested join columns if within depth limit
            $nestedJoins = $join->joinConfig->joins;
            if ($depth < $this->configuration->joinDepth && !empty($nestedJoins)) {
                // Create nested joins with proper aliases
                $nestedWithAliases = [];
                foreach ($nestedJoins as $name => $nestedJoin) {
                    $nestedWithAliases[$name] = $nestedJoin->withParentAlias($join->getAlias());
                }

                $visitedWithCurrent = $visited;
                if ($modelClass) {
                    $visitedWithCurrent[] = $modelClass;
                }

                $dataLine = $this->stripJoinedColumns(
                    $dataLine,
                    $nestedWithAliases,
                    $depth + 1,
                    $visitedWithCurrent
                );
            }
        }

        return $dataLine;
    }

    /**
     * Hydrate relation properties using factories declared in JOINs.
     *
     * For each JOIN definition, if the mapped data contains a string UUID for that relation,
     * it is resolved to the actual model instance via the factory (using preloaded data).
     */
    private function hydrateRelations(array $mappedData): array
    {
        foreach ($this->configuration->joins as $relationName => $join) {
            $value = $mappedData[$relationName] ?? null;

            // Skip if null, already an object, or not a string UUID
            if (null === $value || !is_string($value)) {
                continue;
            }

            // Hydrate via the factory (uses preloaded data from preloader)
            $mappedData[$relationName] = $join->factory
                ->query()
                ->filterBy('uuid', $value)
                ->first();
        }

        return $mappedData;
    }

    /**
     * Resolve join filters: convert "relation.field" to "alias.column".
     */
    private function resolveJoinFilters(array $filters): array
    {
        $resolved = [];

        foreach ($filters as $key => $value) {
            $joinInfo = $this->configuration->resolveJoinFilter($key);

            if ($joinInfo) {
                // Convert to qualified column name: alias.snake_case_field
                $qualifiedKey = $joinInfo['join']->getAlias().'.'.$this->toSnakeCase($joinInfo['field']);
                $resolved[$qualifiedKey] = $value;
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Normalize parameter keys: replace dots with underscores for PDO binding.
     */
    private function normalizeParamKeys(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            $normalizedKey = str_replace('.', '_', $key);
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    public function onModelSync(Middleware $chain, SyncCommand $command): \Generator
    {
        $data = $this->configuration->modelToTableMapper?->map($command->model)
            ?? get_object_vars($command->model)
        ;

        $syncBag = $chain->isLast ?
            [] :
            iterator_to_array(($chain->next)())
        ;

        $syncBag[$this->configuration->dataChannel] = iterator_to_array($this->query(
            $this->sync($data),
            $data
        ));

        yield $syncBag;
    }
}
