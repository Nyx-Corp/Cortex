<?php

namespace Cortex\Component\Model\Factory;

use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Collection\StructuredMap;
use Cortex\Component\Middleware\Middleware;
use Cortex\Component\Middleware\MiddlewareChain;
use Cortex\Component\Model\Factory\Builder\CreationBuilder;
use Cortex\Component\Model\Factory\Mapper\FlatModelMapper;
use Cortex\Component\Model\Factory\Mapper\ModelMapper;
use Cortex\Component\Model\Query\Factory\QueryFactory;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\ValueObject\RegisteredClass;

class ModelFactory
{
    public readonly RegisteredClass $modelClass;
    public readonly RegisteredClass $collectionClass;
    private ModelMapper $mapper;

    private MiddlewareChain $creationMiddlewares;
    private MiddlewareChain $fetchingMiddlewares;
    public private(set) ModelPrototype $modelPrototype;

    public function __construct(
        RegisteredClass|string $modelClass,
        private QueryFactory $queryFactory,
        ?ModelMapper $mapper = null,
        RegisteredClass|string $collectionClass = AsyncCollection::class,
        array $creationMiddlewares = [],
        array $fetchingMiddlewares = [],
    ) {
        $this->modelClass = is_string($modelClass) ? new RegisteredClass($modelClass) : $modelClass;
        $this->collectionClass = is_string($collectionClass) ? new RegisteredClass($collectionClass) : $collectionClass;
        $this->mapper = $mapper ?? new FlatModelMapper();

        $this->modelPrototype = new ModelPrototype($this->modelClass);

        $this->creationMiddlewares = new MiddlewareChain(
            new Middleware(
                fn ($chain, CreationCommand $command) => yield ['_default' => $command->arguments->all()],
                priority: 0   // always run last
            ),
            ...$creationMiddlewares
        );

        $this->fetchingMiddlewares = new MiddlewareChain(...$fetchingMiddlewares);
    }

    private function assemble(?ModelPrototype $prototype)
    {
        if (!$prototype) {
            return null;
        }

        $model = new ($prototype->modelClass->value)(
            ...$prototype->constructors->all()
        );
        $prototype->callbacks->map(
            fn (callable $callback) => $callback($model)
        );

        return $model;
    }

    private function instancePipeline(array $modelData)
    {
        return $this->assemble(
            $this->mapper->prototype(
                clone $this->modelPrototype,
                $modelData
            )
        );
    }

    public function create(): CreationBuilder
    {
        return new CreationBuilder(
            filters: $this->modelPrototype->constructors->prototype(),
            builder: fn (StructuredMap $arguments) => $this->creationMiddlewares
                ->compile(new CreationCommand($this->modelClass, $arguments))
                ->map(fn (array $modelData) => $this->instancePipeline($modelData))
                ->filter(fn ($model) => null !== $model)
                ->first()
        );
    }

    public function query(): ModelQuery
    {
        return $this->queryFactory->createQuery(
            modelCollectionClass: $this->collectionClass,
            filters: $this->modelPrototype->constructors->prototype(),
            resolver: fn (ModelQuery $query) => $this->fetchingMiddlewares
                ->compile($query)
                ->map(fn (array $modelData) => $this->instancePipeline($modelData))
                ->filter(fn ($model) => null !== $model)
        );
    }
}
