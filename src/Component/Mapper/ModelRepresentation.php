<?php

declare(strict_types=1);

namespace Cortex\Component\Mapper;

/**
 * Defines how a domain model is represented in different contexts.
 *
 * Each group is a named view of the model (store, list, detail, id...).
 * The 'store' group is required when a DbalMapper exists for the model.
 *
 * Annotate implementations with #[Model(MyModel::class)] to register them.
 *
 * @see ArrayMapper
 */
interface ModelRepresentation
{
    /**
     * Outbound mapper: model → array for a given group.
     *
     * Each group can have its own mapper (store = snake+FK, default = camelCase).
     * Groups without a dedicated mapper fall back to 'default'.
     */
    public function writer(string $group = 'default'): ArrayMapper;

    /**
     * Inbound mapper: array → model data for a given group.
     *
     * store = DB row → model data, default = API input → model data.
     */
    public function reader(string $group = 'default'): ArrayMapper;

    /**
     * Group definitions: field inclusion lists.
     *
     * Each group is a list of field specs to include.
     * null value = all fields (no filtering).
     *
     * Field spec syntax (inspired by MajoraFramework/Normalizer, Nyxis ~2015):
     *
     *   'field'          — include the field as-is
     *   'field@group'    — include + propagate group to the relation recursively
     *   '@group'         — inheritance: include all fields from another group
     *   '?field'         — optional: omit from result if value is null
     *   '?field@group'   — optional + propagation combo
     *
     * Resolution order: last wins (allows overriding inherited fields).
     *
     * @return array<string, list<string>|null>
     */
    public function groups(): array;

    /**
     * Groups accessible without any scope (public by default).
     *
     * Override to restrict or expand public access.
     *
     * @return list<string>
     */
    public function publicGroups(): array;
}
