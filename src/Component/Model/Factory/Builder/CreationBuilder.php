<?php

namespace Cortex\Component\Model\Factory\Builder;

/**
 * @internal
 */
class CreationBuilder extends Builder
{
    public function with(...$filter): self
    {
        return $this->addFilter(...$filter);
    }

    public function filterBy(string $attribute, mixed $value): self
    {
        $this->filters->set($attribute, $value);

        return $this;
    }

    public function build()
    {
        return $this->endBuild();
    }
}
