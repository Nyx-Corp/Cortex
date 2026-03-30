<?php

declare(strict_types=1);

namespace Cortex\Component\Model\Query;

/**
 * Result of FilterQueryParser::parse() — structured filter, sort, and limit.
 */
final readonly class ParsedFilterQuery
{
    /**
     * @param array<string, string> $filters Raw field → value pairs (caller maps to DB columns)
     * @param ?Sorter               $sort    Parsed sort directive, or null
     * @param int                   $limit   Parsed limit (capped)
     */
    public function __construct(
        public array $filters,
        public ?Sorter $sort,
        public int $limit,
    ) {
    }

    /**
     * Map raw filter field names to DB column names.
     *
     * @param array<string, string> $fieldMap Raw field name → DB column name
     *
     * @return array<string, string> DB column → value (unknown fields silently dropped)
     */
    public function mapFilters(array $fieldMap): array
    {
        $mapped = [];

        foreach ($this->filters as $field => $value) {
            $column = $fieldMap[$field] ?? null;

            if (null !== $column) {
                $mapped[$column] = $value;
            }
        }

        return $mapped;
    }
}
