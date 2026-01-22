<?php

namespace Cortex\Bridge\Symfony\Form\DataTransformer;

use Cortex\ValueObject\Email;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class EmailTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof Email) {
            return (string) $value;
        }

        return (string) $value;
    }

    public function reverseTransform(mixed $value): ?Email
    {
        if (empty($value)) {
            return null;
        }

        try {
            return new Email($value);
        } catch (\InvalidArgumentException $e) {
            throw new TransformationFailedException($e->getMessage());
        }
    }
}
