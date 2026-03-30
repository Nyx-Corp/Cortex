<?php

declare(strict_types=1);

namespace Cortex\Component\Model\Query;

/**
 * Parses Gmail-style filter strings into structured query parameters.
 *
 * Syntax:  field:value field2:"value with spaces" sort:name_desc limit:5
 *
 * - Regular tokens (field:value) are collected as filters
 * - `sort:field_asc` / `sort:field_desc` → Sorter (default ASC)
 * - `limit:N` → integer (capped 1–max, default 10)
 *
 * Field names are raw — callers map them to DB columns via their own mapping.
 *
 * @see ModelQueryDecorator::parseQueryString() — similar but coupled to form filters
 */
final readonly class FilterQueryParser
{
    public function __construct(
        private int $defaultLimit = 10,
        private int $maxLimit = 100,
    ) {
    }

    public function parse(string $query): ParsedFilterQuery
    {
        if ('' === $query) {
            return new ParsedFilterQuery([], null, $this->defaultLimit);
        }

        $pattern = '/(\w+):(?:"([^"]+)"|(\S+))/';
        preg_match_all($pattern, $query, $matches, \PREG_SET_ORDER);

        $filters = [];
        $sort = null;
        $limit = $this->defaultLimit;

        foreach ($matches as $match) {
            $field = $match[1];
            $value = '' !== $match[2] ? $match[2] : $match[3];

            if ('limit' === $field) {
                $limit = max(1, min((int) $value, $this->maxLimit));
                continue;
            }

            if ('sort' === $field) {
                $sort = self::parseSorter($value);
                continue;
            }

            $filters[$field] = $value;
        }

        return new ParsedFilterQuery($filters, $sort, $limit);
    }

    private static function parseSorter(string $value): Sorter
    {
        if (str_ends_with($value, '_desc')) {
            return new Sorter(substr($value, 0, -5), SortDirection::DESC);
        }

        if (str_ends_with($value, '_asc')) {
            return new Sorter(substr($value, 0, -4), SortDirection::ASC);
        }

        return new Sorter($value, SortDirection::ASC);
    }
}
