<?php

namespace Cortex\Component\Model\Query;

use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Model\ModelCollection;
use Cortex\ValueObject\RegisteredClass;

class ModelQuery
{
    private \Closure $resolver;

    public private(set) StructuredMap $filters;
    public private(set) ?Sorter $sorter = null;
    public private(set) ?Pager $pager = null;
    public private(set) StructuredMap $tags;
    public private(set) ?int $limit = null;
    public private(set) ?string $uniqueField = null;
    public private(set) array $nullFields = [];
    public private(set) array $notNullFields = [];

    public function __construct(
        \Closure $resolver,
        public readonly RegisteredClass $modelCollectionClass,
        ?StructuredMap $filters = null,
        ?StructuredMap $tags = null,
    ) {
        $this->modelCollectionClass->assertIsInstanceOf(ModelCollection::class);
        $this->resolver = $resolver;

        $this->filters = $filters ?? new StructuredMap();
        $this->tags = $tags ?? new StructuredMap();
    }

    public function filterBy(string $attribute, mixed $value): self
    {
        if (is_array($value)) {
            $this->filters->add($attribute, $value);
        } else {
            $this->filters->set($attribute, $value);
        }

        return $this;
    }

    public function filter(...$filters): self
    {
        if (empty($filters)) {
            return $this;
        }
        if (array_is_list($filters)) {
            throw new \InvalidArgumentException('Query filters must be provided as named arguments.');
        }

        foreach ($filters as $attribute => $value) {
            $this->filterBy($attribute, $value);
        }

        return $this;
    }

    public function filterNull(string $field): self
    {
        $this->nullFields[] = $field;

        return $this;
    }

    public function filterNotNull(string $field): self
    {
        $this->notNullFields[] = $field;

        return $this;
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function paginate(?Pager $pager): self
    {
        $this->pager = $pager;

        return $this;
    }

    public function sort(?Sorter $sorter): self
    {
        $this->sorter = $sorter;

        return $this;
    }

    /**
     * Set a unique field for deduplication (translates to GROUP BY).
     *
     * @param string|null $field Model field name (e.g., 'contact' for unique contacts)
     */
    public function unique(?string $field = null): self
    {
        $this->uniqueField = $field;

        return $this;
    }

    public function tag(...$tags): self
    {
        if (empty($tags)) {
            return $this;
        }
        if (array_is_list($tags)) {
            throw new \InvalidArgumentException('Query tags must be provided as named arguments.');
        }

        foreach ($tags as $attribute => $value) {
            $this->tags->set($attribute, $value);
        }

        return $this;
    }

    /**
     * Apply a Gmail-style filter query string.
     *
     * Syntax: "field:value sort:name_desc limit:5"
     *
     * @param string                $query    Gmail-style query string
     * @param array<string, string> $fieldMap Raw field names → DB column names
     */
    public function applyFilterQuery(string $query, array $fieldMap = []): self
    {
        $parsed = (new FilterQueryParser())->parse($query);

        $filters = [] !== $fieldMap ? $parsed->mapFilters($fieldMap) : $parsed->filters;

        foreach ($filters as $column => $value) {
            $this->filterBy($column, $value);
        }

        if (null !== $parsed->sort) {
            $this->sort($parsed->sort);
        }

        $this->limit($parsed->limit);

        return $this;
    }

    public function getCollection(): ModelCollection
    {
        return ModelCollection::build($this)
            ->as($this->modelCollectionClass)
        ;
    }

    public function all(): ModelCollection
    {
        return $this->getCollection();
    }

    public function first(): ?object
    {
        return $this->getCollection()->first();
    }

    public function resolve(): \Generator
    {
        yield from ($this->resolver)($this);
    }
}
