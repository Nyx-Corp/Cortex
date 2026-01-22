<?php

namespace Cortex\Component\Model\Factory\Builder;

use Cortex\Component\Collection\StructuredMap;

abstract class Builder
{
    public readonly StructuredMap $filters;

    public function __construct(
        private \Closure $builder,
        ?StructuredMap $filters = null,
    ) {
        $this->filters = $filters ?? new StructuredMap();
    }

    protected function endBuild(...$args)
    {
        $builder = $this->builder;

        return $builder($this->filters, ...$args);
    }

    protected function addFilter(...$filters): self
    {
        if (array_is_list($filters)) {
            throw new \InvalidArgumentException('Filters must be provided as named arguments.');
        }

        foreach ($filters as $attribute => $value) {
            $this->filterBy($attribute, $value);
        }

        return $this;
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
}
