<?php

declare(strict_types=1);

namespace Cortex\Bridge\Symfony\Serializer;

use Cortex\Bridge\Symfony\Serializer\Event\PreDenormalizeEvent;
use Cortex\Bridge\Symfony\Serializer\Event\PreNormalizeEvent;
use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\ModelRepresentation;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Bridges Cortex ModelRepresentation into the Symfony Serializer ecosystem.
 *
 * Uses ArrayMapper-based representations with group support, recursive
 * propagation, inheritance (@group), and optional fields (?field).
 *
 * Dispatches PreNormalizeEvent before normalization to allow listeners
 * (e.g., Gandalf security) to modify the group.
 */
class CortexNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly RepresentationRegistry $registry,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $requestedGroup = $context['cortex_group'] ?? 'default';

        $event = new PreNormalizeEvent($data::class, $data, $requestedGroup);
        $this->dispatcher->dispatch($event);
        $group = $event->getGroup();

        if ($group !== $requestedGroup) {
            $this->logger->info('Normalize group resolved for {model}: {requested} → {resolved}', [
                'model' => $data::class,
                'requested' => $requestedGroup,
                'resolved' => $group,
            ]);
        }

        $representation = $this->registry->get($data::class);
        $rawData = is_object($data) ? get_object_vars($data) : (array) $data;

        // Map only scalar-compatible values through the writer (objects are kept raw for propagation)
        $mapper = $representation->writer($group);
        $mappedData = $this->safeMap($mapper, $rawData);

        $groupDef = $representation->groups()[$group] ?? null;
        if ($groupDef === null) {
            return $mappedData;
        }

        $fields = $this->resolveGroup($representation, $groupDef);

        $filtered = [];
        foreach ($fields as $field => ['subGroup' => $subGroup, 'optional' => $optional]) {
            $hasMapped = array_key_exists($field, $mappedData);
            $hasRaw = array_key_exists($field, $rawData);

            if (!$hasMapped && !$hasRaw) {
                continue;
            }

            $value = $hasMapped ? $mappedData[$field] : $rawData[$field];

            if ($optional && $value === null) {
                continue;
            }

            if ($subGroup !== null) {
                $filtered[$field] = $this->propagate($rawData[$field] ?? $value, $format, $subGroup);

                continue;
            }

            $filtered[$field] = $value;
        }

        return $filtered;
    }

    /**
     * Map data through the ArrayMapper, filtering out objects that the mapper can't handle.
     * Unmappable objects (relations, collections) are handled by propagation instead.
     */
    private function safeMap(ArrayMapper $mapper, array $data): array
    {
        $mappable = [];
        foreach ($data as $key => $value) {
            if (is_object($value)
                && !$value instanceof \Stringable
                && !$value instanceof \BackedEnum
                && !$value instanceof \JsonSerializable
                && !$value instanceof \DateTimeInterface
            ) {
                continue;
            }

            if (is_array($value) && !empty($value) && is_object(reset($value))) {
                continue;
            }

            $mappable[$key] = $value;
        }

        return $mapper->map($mappable);
    }

    /**
     * Resolve a group definition: flatten @inheritance, parse field@group and ?field.
     * Last wins (allows overriding inherited fields).
     *
     * @return array<string, array{subGroup: ?string, optional: bool}>
     */
    private function resolveGroup(ModelRepresentation $representation, array $groupDef): array
    {
        $fields = [];
        $allGroups = $representation->groups();

        foreach ($groupDef as $spec) {
            if (str_starts_with($spec, '@')) {
                $inheritedGroup = substr($spec, 1);
                $inheritedDef = $allGroups[$inheritedGroup] ?? null;
                if ($inheritedDef !== null) {
                    $fields = array_merge($fields, $this->resolveGroup($representation, $inheritedDef));
                }

                continue;
            }

            $optional = false;
            if (str_starts_with($spec, '?')) {
                $optional = true;
                $spec = substr($spec, 1);
            }

            $subGroup = null;
            if (str_contains($spec, '@')) {
                [$spec, $subGroup] = explode('@', $spec, 2);
            }

            $fields[$spec] = ['subGroup' => $subGroup, 'optional' => $optional];
        }

        return $fields;
    }

    private function propagate(mixed $value, ?string $format, string $subGroup): mixed
    {
        if (is_object($value) && $this->registry->has($value::class)) {
            return $this->normalize($value, $format, ['cortex_group' => $subGroup]);
        }

        if (is_iterable($value)) {
            $items = is_array($value) ? $value : iterator_to_array($value);

            return array_map(
                fn (mixed $item): mixed => is_object($item) && $this->registry->has($item::class)
                    ? $this->normalize($item, $format, ['cortex_group' => $subGroup])
                    : $item,
                $items,
            );
        }

        return $value;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): array
    {
        $requestedGroup = $context['cortex_group'] ?? 'default';

        $event = new PreDenormalizeEvent($type, (array) $data, $requestedGroup);
        $this->dispatcher->dispatch($event);
        $group = $event->getGroup();

        if ($group !== $requestedGroup) {
            $this->logger->info('Denormalize group resolved for {model}: {requested} → {resolved}', [
                'model' => $type,
                'requested' => $requestedGroup,
                'resolved' => $group,
            ]);
        }

        return $this->registry->get($type)->reader($group)->map($data);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_object($data) && $this->registry->has($data::class);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_string($type) && $this->registry->has($type);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
