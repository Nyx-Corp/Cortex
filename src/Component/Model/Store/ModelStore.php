<?php

namespace Cortex\Component\Model\Store;

use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Middleware\MiddlewareChain;
use Cortex\Component\Model\Factory\Mapper\FlatModelMapper;
use Cortex\Component\Model\Factory\Mapper\ModelMapper;
use Cortex\ValueObject\RegisteredClass;

class ModelStore
{
    private RegisteredClass $modelClass;
    private RegisteredClass $collectionClass;
    private ModelMapper $mapper;

    private MiddlewareChain $syncMiddlewares;
    private MiddlewareChain $removeMiddlewares;

    public function __construct(
        RegisteredClass|string $modelClass,
        ?ModelMapper $mapper = null,
        RegisteredClass|string $collectionClass = AsyncCollection::class,
        array $syncMiddlewares = [],
        array $removeMiddlewares = [],
    ) {
        $this->modelClass = is_string($modelClass) ? new RegisteredClass($modelClass) : $modelClass;
        $this->collectionClass = is_string($collectionClass) ? new RegisteredClass($collectionClass) : $collectionClass;
        $this->mapper = $mapper ?? new FlatModelMapper();

        $this->syncMiddlewares = new MiddlewareChain(
            ...$syncMiddlewares
        );

        $this->removeMiddlewares = new MiddlewareChain(
            ...$removeMiddlewares
        );
    }

    private function process(MiddlewareChain $middlewareChain, SyncCommand|RemoveCommand $command): ?array
    {
        return $middlewareChain->compile($command)->first();
    }

    public function sync($model): ?array
    {
        $this->modelClass->assertInstanceOf($model);

        return $this->process(
            $this->syncMiddlewares,
            new SyncCommand($model, $this->modelClass)
        );
    }

    public function remove($model): ?array
    {
        $this->modelClass->assertInstanceOf($model);

        return $this->process(
            $this->removeMiddlewares,
            new RemoveCommand($model, $this->modelClass)
        );
    }
}
