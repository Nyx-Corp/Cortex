<?php

declare(strict_types=1);

namespace Cortex\Bridge\Symfony\Serializer;

use Cortex\Component\Mapper\ModelRepresentation;

/**
 * Collects ModelRepresentation instances, keyed by model class.
 *
 * Populated by ModelProcessorCompilerPass via autoconfigure tag.
 */
class RepresentationRegistry
{
    /** @var array<class-string, ModelRepresentation> */
    private array $representations = [];

    /** @var list<class-string> */
    private array $storeRequired = [];

    /** @var array<class-string, bool> */
    private array $storeValidated = [];

    public function register(string $modelClass, ModelRepresentation $representation): void
    {
        $this->representations[$modelClass] = $representation;
    }

    /**
     * Mark a model as requiring a 'store' group (called by CompilerPass when a DbalMapper exists).
     */
    public function requireStoreGroup(string $modelClass): void
    {
        $this->storeRequired[] = $modelClass;
    }

    public function has(string $modelClass): bool
    {
        return isset($this->representations[$modelClass]);
    }

    public function get(string $modelClass): ModelRepresentation
    {
        $representation = $this->representations[$modelClass]
            ?? throw new \InvalidArgumentException(sprintf('No ModelRepresentation registered for "%s".', $modelClass));

        if (in_array($modelClass, $this->storeRequired, true)
            && !isset($this->storeValidated[$modelClass])
        ) {
            $groups = $representation->groups();
            if (!isset($groups['store'])) {
                throw new \LogicException(sprintf(
                    'ModelRepresentation for "%s" must define a "store" group (required by DbalMapper).',
                    $modelClass,
                ));
            }
            $this->storeValidated[$modelClass] = true;
        }

        return $representation;
    }
}
