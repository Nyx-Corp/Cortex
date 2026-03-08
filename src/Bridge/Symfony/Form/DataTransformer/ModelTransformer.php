<?php

namespace Cortex\Bridge\Symfony\Form\DataTransformer;

use Cortex\Component\Model\Factory\ModelFactory;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class ModelTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly ModelFactory $factory,
    ) {
    }

    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value->uuid ? (string) $value->uuid : null;
    }

    public function reverseTransform(mixed $value): ?object
    {
        if (empty($value)) {
            return null;
        }

        $model = $this->factory->query()->filter(uuid: $value)->first();

        if (null === $model) {
            throw new TransformationFailedException(sprintf('Model with uuid "%s" not found.', $value));
        }

        return $model;
    }
}
