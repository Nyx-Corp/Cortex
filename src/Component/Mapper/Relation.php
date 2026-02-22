<?php

namespace Cortex\Component\Mapper;

/**
 * Represents a relation mapping for FK columns.
 *
 * Usage in modelToTableMapper:
 *   'organisation' => Relation::toUuid('organisation_uuid'),  // maps ->organisation->uuid to organisation_uuid column
 *   'parent' => Relation::toUuid('parent_uuid', nullable: true),  // nullable FK
 *
 * Usage in tableToModelMapper:
 *   'organisation_uuid' => Relation::toModel('organisation'),  // maps organisation_uuid to ->organisation property
 */
final class Relation
{
    private function __construct(
        public readonly string $column,
        public readonly bool $nullable = false,
        public readonly string $property = 'uuid',
    ) {
    }

    /**
     * Maps a model relation to a UUID column.
     * Extracts ->uuid from the related object and stores it in the specified column.
     */
    public static function toUuid(string $column, bool $nullable = false): self
    {
        return new self($column, $nullable, 'uuid');
    }

    /**
     * Maps a table UUID column back to a model property name.
     * Used in tableToModelMapper to rename the column to the model property.
     */
    public static function toModel(string $property, bool $nullable = false): self
    {
        return new self($property, $nullable, $property);
    }
}
