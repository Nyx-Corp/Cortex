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
    case IsNull = 'IS_NULL';
    case IsNotNull = 'IS_NOT_NULL';

    public function toSql(): string
    {
        return match ($this) {
            self::Like => 'LIKE',
            self::NotLike => 'NOT LIKE',
            self::IsNull => 'IS NULL',
            self::IsNotNull => 'IS NOT NULL',
            default => $this->value,
        };
    }

    public function isUnary(): bool
    {
        return match ($this) {
            self::IsNull, self::IsNotNull => true,
            default => false,
        };
    }

    public static function pattern(): string
    {
        return implode(
            '|',
            array_map(
                static fn (Operator $op) => preg_quote($op->value),
                array_filter(self::cases(), static fn (Operator $op) => !$op->isUnary())
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
