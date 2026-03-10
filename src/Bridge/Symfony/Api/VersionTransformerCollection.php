<?php

namespace Cortex\Bridge\Symfony\Api;

class VersionTransformerCollection
{
    /** @var array<string, VersionTransformerInterface> */
    private array $transformers = [];

    public function __construct(iterable $transformers)
    {
        foreach ($transformers as $transformer) {
            $this->transformers[$transformer->getCommandClass()] = $transformer;
        }
    }

    public function transformRequest(string $commandClass, array $data, int $version): array
    {
        if (!isset($this->transformers[$commandClass])) {
            return $data;
        }

        return $this->transformers[$commandClass]->transformRequest($data, $version);
    }

    public function transformResponse(string $commandClass, mixed $data, int $version): mixed
    {
        if (!isset($this->transformers[$commandClass])) {
            return $data;
        }

        return $this->transformers[$commandClass]->transformResponse($data, $version);
    }
}
