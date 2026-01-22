<?php

namespace Cortex\Bridge\Symfony\Collection;

use Cortex\Component\Collection\AsyncCollection;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Wrapper class for collection controls like :
 *      - filters
 *      - sorts
 *      - pagination
 */
class CollectionDecorator
{
    public function __construct(
        public readonly AsyncCollection $collection,
        private readonly FormBuilderInterface $formBuilder,
    ) {
    }

    public function build(?callable $filterBuilder = null): Form
    {
        if ($filterBuilder) {
            $filterBuilder($this->formBuilder->get('_filters'));
        }

        return $this->formBuilder->getForm();
    }
}
