<?php

namespace Cortex\Component\Mapper;

use Cortex\Component\Date\DateString;
use Cortex\Component\Json\JsonString;

use function Symfony\Component\String\u;

class ArrayMapper implements Mapper
{
    private array $mapping;

    public function __construct(
        array $mapping = [],
        array $sourceKeys = [],
        private Strategy $format = Strategy::AutoMapSnake,
        private Strategy $automap = Strategy::AutoMapAll,
    ) {
        $this->mapping = array_combine(
            array_map(fn (string $key) => $this->strategize($key), array_keys($mapping)),
            array_values($mapping),
        );

        foreach ($sourceKeys as $sourceKey) {
            $key = $this->strategize($sourceKey);
            if (!isset($this->mapping[$key])) {
                $this->mapping[$key] = $sourceKey;
            }
        }
    }

    private function strategize(string $string): string
    {
        return Strategy::AutoMapCamel === $this->format
            ? u($string)->camel()->toString()
            : u($string)->snake()->toString();
    }

    private function isEnumClass(mixed $value): bool
    {
        return is_string($value)
            && class_exists($value)
            && is_subclass_of($value, \BackedEnum::class);
    }

    private function mapValue(mixed $value, string $sourceKey, string $destKey): mixed
    {
        if (is_scalar($value) || null === $value) {
            return $value;
        }

        return match (true) {
            $value instanceof \Stringable => (string) $value,
            is_array($value) => $value,
            $value instanceof \JsonSerializable => (string) new JsonString($value),
            $value instanceof \BackedEnum => $value->value,
            default => throw new \InvalidArgumentException(sprintf('Value under key "%s" cannot be mapped to "%s" (need scalar, date, stringable, BackedEnum or JSON-serializable), got: %s', $sourceKey, $destKey, get_debug_type($value))),
        };
    }

    public function map($source, &$result = [], ...$context): array
    {
        if (is_object($source)) {
            $source = get_object_vars($source);
        }
        if (!is_array($source)) {
            throw new \InvalidArgumentException(sprintf('Source must be an array or object, got: %s', get_debug_type($source)));
        }

        // Track already mapped keys to avoid reprocessing
        $explicitlyMapped = [];

        foreach ($this->mapping as $destKey => $mapping) {
            if ($mapping instanceof Value || $mapping instanceof Relation || is_callable($mapping) || $this->isEnumClass($mapping)) {
                continue;
            }

            if (!array_key_exists($mapping, $source)) {
                continue;
            }

            $explicitlyMapped[] = $mapping;
            $result[$destKey] = $this->mapValue($source[$mapping], $mapping, $destKey);
        }

        if (Strategy::AutoMapAll === $this->automap) {
            foreach ($source as $sourceKey => $value) {
                $destKey = $this->strategize($sourceKey);

                if (in_array($destKey, $explicitlyMapped, true)) {
                    continue;
                }

                if (isset($this->mapping[$destKey]) && Value::Ignore === $this->mapping[$destKey]) {
                    continue;
                }

                if (array_key_exists($destKey, $result)) {
                    continue;
                }

                if (isset($this->mapping[$destKey]) && Value::Json === $this->mapping[$destKey]) {
                    $jsonValue = new JsonString($value);
                    $result[$destKey] = is_string($value) ? $jsonValue->decode() : (string) $jsonValue;
                    continue;
                }

                if (isset($this->mapping[$destKey]) && Value::Date === $this->mapping[$destKey]) {
                    if (null === $value || '' === $value) {
                        $result[$destKey] = null;
                        continue;
                    }
                    $dateValue = new DateString($value);
                    $result[$destKey] = is_string($value) ? $dateValue->parse() : $dateValue->format();
                    continue;
                }

                if (isset($this->mapping[$destKey]) && Value::Bool === $this->mapping[$destKey]) {
                    $result[$destKey] = is_bool($value) ? (int) $value : (bool) $value;
                    continue;
                }

                // Enum class: bidirectional conversion
                if (isset($this->mapping[$destKey]) && $this->isEnumClass($this->mapping[$destKey])) {
                    /** @var class-string<\BackedEnum> $enumClass */
                    $enumClass = $this->mapping[$destKey];
                    $result[$destKey] = $value instanceof \BackedEnum
                        ? $value->value
                        : $enumClass::from($value);
                    continue;
                }

                // Relation: FK mapping with column renaming
                if (isset($this->mapping[$destKey]) && $this->mapping[$destKey] instanceof Relation) {
                    $relation = $this->mapping[$destKey];
                    $targetColumn = $relation->column;

                    // Skip if nullable and value is null
                    if (null === $value && $relation->nullable) {
                        $result[$targetColumn] = null;
                        continue;
                    }

                    // Extract UUID from related object or use value directly (for tableToModel)
                    if (is_object($value) && property_exists($value, $relation->property)) {
                        $result[$targetColumn] = (string) $value->{$relation->property};
                    } elseif (is_string($value)) {
                        // tableToModel case: just rename the key
                        $result[$targetColumn] = $value;
                    } elseif (null === $value && $relation->nullable) {
                        $result[$targetColumn] = null;
                    }
                    continue;
                }

                if (array_key_exists($destKey, $this->mapping) && is_callable($this->mapping[$destKey])) {
                    $result[$destKey] = ($this->mapping[$destKey])($value, $destKey, ...$context);
                    continue;
                }

                $result[$destKey] = $this->mapValue($value, $sourceKey, $destKey);
            }
        }

        return $result;
    }
}
