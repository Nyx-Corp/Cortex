<?php

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
    ) {
        self::$operatorFilterPattern ??= '/^('.Operator::pattern().')/';
    }

    public function query(string $query, array $params = []): \Generator
    {
        $stmt = $this->dbalConnection->executeQuery(
            $query,
            array_map(
                static fn ($param) => match(true) {
                    $param === null => null,
                    is_scalar($param) => preg_filter(self::$operatorFilterPattern, '', $param) ?? $param,
                    is_array($param) => $param,
                    default => (string) $param,
                },
                $params
            ),
            array_map(
                static fn ($param) => match(true) {
                    $param === null => ParameterType::NULL,
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

    public function where(array $params): string
    {
        return count($params) ? ' WHERE '.implode(' AND ', array_map(
            function ($key, $value): string {
                if (is_array($value)) {
                    return sprintf('%s IN (:%s)', $key, $key);
                }
                if (is_null($value)) {
                    return sprintf('%s IS NULL', $key);
                }

                $operator = array_find(
                    Operator::cases(),
                    static fn (Operator $op) => str_starts_with($value, $op->value)
                ) ?: Operator::Equal;

                return sprintf('%s %s :%s', $key, $operator->toSql(), $key);
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
    ): string {
        return trim(sprintf(
            'SELECT %s FROM %s%s%s%s%s',
            $fields,
            $this->configuration->table,
            $this->where($params),
            $sortBy ? ' ORDER BY '.$sortBy.' '.strtoupper($sortDirection) : '',
            $limit > 0 ? ' LIMIT '.$limit : '',
            $limit > 0 && $offset !== null ? ' OFFSET '.$offset : '',
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

    public function onModelQuery(Middleware $chain, ModelQuery $query): \Generator
    {
        $filters = $this->configuration->modelToTableMapper?->map($query->filters->all())
            ?? $query->filters->all()
        ;

        $limit = $query->limit ?? 0;

        if ($query->pager && !$limit) {
            $count = iterator_to_array($this->query($this->select($filters, 'COUNT(*) AS count'), $filters))[0]['count'] ?? 0;
            $query->pager->bind($count);

            if ($count === 0) {
                yield from [];

                return; // no results
            }
        }

        if ($query->sorter) {
            // @fixme hack with now date just to convert key
            $sortBy = array_key_first(
                $this->configuration->modelToTableMapper?->map([
                    $query->sorter->field => new \DateTimeImmutable(),
                ])
            );
        }

        $results = AsyncCollection::create(
            $this->query(
                $this->select(
                    $filters,
                    limit: $limit ?? $query->pager?->limit ?? 0,
                    offset: $query->pager?->offset ?? null,
                    sortBy: $sortBy ?? null,
                    sortDirection: $query->sorter?->direction->value ?? 'asc',
                ),
                $filters
            )
        );

        // last middleware in the chain => yield directly
        if ($chain->isLast) {
            foreach ($results as $dataLine) {
                $identifierValue = $dataLine[$this->configuration->pivotKey] ?? null;
                if ($identifierValue === null) {
                    throw new \InvalidArgumentException(sprintf('Identifier "%s" not found in data line: %s', $this->configuration->pivotKey, new JsonString($dataLine)));
                }

                yield $identifierValue => [
                    $this->configuration->dataChannel => $this->configuration
                        ->tableToModelMapper->map($dataLine),
                ];
            }

            return;
        }

        // try to map results to next middleware
        foreach ($chain->next() as $identifier => $dataLine) {

            // find the identifier in results
            $resultLine = $results->find(static fn ($line) => $line[$this->configuration->pivotKey] === $identifier);
            if ($resultLine === null) {
                // log ?
                yield $identifier => $dataLine; // pass through unchanged
                continue;
            }

            yield $identifier => array_replace_recursive(
                $dataLine,
                [
                    '_dbal' => $resultLine,
                    $this->configuration->dataChannel => $this->configuration->tableToModelMapper->map($resultLine),
                ],
            );
        }
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
