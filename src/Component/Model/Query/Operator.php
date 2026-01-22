<?php

namespace Cortex\Component\Model\Query;

enum Operator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Like = '~';
    case NotLike = '!~';

    public function toSql(): string
    {
        return match ($this) {
            self::Like => 'LIKE',
            self::NotLike => 'NOT LIKE',
            default => $this->value,
        };
    }

    public static function pattern(): string
    {
        return implode(
            '|',
            array_map(
                static fn (Operator $op) => preg_quote($op->value),
                self::cases()
            )
        );
    }

    /**
     * Checks if a value starts with a valid operator.
     */
    public static function hasOperator(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return (bool) preg_match('/^('.self::pattern().')/', $value);
    }
}
