<?php

namespace Cortex\ValueObject;

class RegisteredClass extends ValueObject
{
    public static function exists(string $value): bool
    {
        return class_exists($value) || interface_exists($value);
    }

    public function __construct(string $value)
    {
        if (false === self::exists($value)) {
            throw new \InvalidArgumentException(sprintf('Class or interface "%s" does not exists.', $value));
        }

        parent::__construct($value);
    }

    public function isInstanceOf(object|string $objectOrClass): bool
    {
        return is_a($this->value, $objectOrClass, true);
    }

    public function assertIsInstanceOf(object|string $objectOrClass): object|string
    {
        if (!$this->isInstanceOf($objectOrClass)) {
            throw new \InvalidArgumentException(sprintf('"%s" registered class is not a "%s".', $this->value, is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass));
        }

        return $objectOrClass;
    }

    public function instanceOf(object|string $objectOrClass): bool
    {
        return is_a($objectOrClass, $this->value, true);
    }

    public function assertInstanceOf(object|string $objectOrClass): object|string
    {
        if (!$this->instanceOf($objectOrClass)) {
            throw new \InvalidArgumentException(sprintf('Expected instance of "%s", got "%s".', $this->value, is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass));
        }

        return $objectOrClass;
    }
}
