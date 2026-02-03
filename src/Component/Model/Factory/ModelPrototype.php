<?php

namespace Cortex\Component\Model\Factory;

use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Model\Query\Operator;
use Cortex\ValueObject\RegisteredClass;

class ModelPrototype
{
    public ?RegisteredClass $modelClass = null {
        set {
            if ($this->modelClass && !is_a($value->value, $this->modelClass->value, true)) {
                throw new \InvalidArgumentException(sprintf('Can only switch classes for a subclass of current one , "%s" given.', $value));
            }

            $this->modelClass = $value;
        }
    }

    public private(set) StructuredMap $constructors;
    public private(set) StructuredMap $callbacks;

    public function __construct(RegisteredClass $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->buildConstructorMap();

        $this->callbacks = new StructuredMap();
    }

    private function buildConstructorMap()
    {
        $reflection = new \ReflectionClass($this->modelClass->value);

        if (!$reflection->isInstantiable()) {
            throw new \InvalidArgumentException(sprintf('Classes has to be instantiable to be prototyped, "%s" defined.', $this->modelClass));
        }
        if (!$constructor = $reflection->getConstructor()) {
            return;
        }

        $this->constructors = new StructuredMap();

        foreach ($constructor->getParameters() as $parameter) {
            $this->constructors->declare(
                $parameter->getName(),
                validation: match ($parameter->getType()?->getName() ?? 'mixed') {
                    'int' => fn ($value) => is_numeric($value) || Operator::hasOperator($value),
                    'string' => 'is_string',
                    'bool' => 'is_bool',
                    'float' => fn ($value) => is_numeric($value) || Operator::hasOperator($value),
                    // Arrays can be filtered with LIKE patterns (string) or exact match (JSON string)
                    'array' => fn ($value) => is_array($value) || is_string($value),
                    'object' => 'is_object',
                    'mixed' => null,
                    'callable' => 'is_callable',
                    default => fn ($value) => is_scalar($value) || is_a($value, $parameter->getType()->getName()),
                },
                nullable: $parameter->allowsNull()
            );

            if ($parameter->isOptional() && !empty($parameter->getDefaultValue())) {
                $this->constructors->set(
                    $parameter->getName(),
                    $parameter->getDefaultValue()
                );
            }
        }
    }

    public function __clone(): void
    {
        $this->modelClass = $this->modelClass;
        $this->constructors = $this->constructors->prototype();
        $this->callbacks = $this->callbacks->prototype();
    }
}
