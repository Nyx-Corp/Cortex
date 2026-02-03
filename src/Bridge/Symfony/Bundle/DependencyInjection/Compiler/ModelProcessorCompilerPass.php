<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler;

use Cortex\Bridge\Symfony\Controller\ModelFactoryValueResolver;
use Cortex\Bridge\Symfony\Model\Attribute\Middleware as MiddlewareAttribute;
use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Middleware\Middleware;
use Cortex\Component\Model\Factory\Mapper\ModelMapper;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\Component\Model\ModelMiddleware;
use Cortex\Component\Model\Scope;
use Cortex\Component\Model\Store\ModelStore;
use Cortex\ValueObject\RegisteredClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ModelProcessorCompilerPass implements CompilerPassInterface
{
    private function getModelClassAttribute(string $serviceClass): ?Model
    {
        $ref = new \ReflectionClass($serviceClass);
        if (!$attribute = $ref->getAttributes(Model::class)[0] ?? null) {
            return null;
        }

        return $attribute->newInstance();
    }

    private function replaceCollectionClass(array &$modelNode, ?RegisteredClass $collectionClass): void
    {
        if (!$collectionClass) {
            return;
        }

        if (isset($modelNode['collection'])
            && $modelNode['collection'] instanceof RegisteredClass
            && !$modelNode['collection']->instanceOf(AsyncCollection::class)
            && !$modelNode['collection']->equals($collectionClass)
        ) {
            throw new \LogicException(sprintf('Model already has "%s" defined as custom Collection, cannot redefine it with "%s".', $modelNode['collection'], $collectionClass));
        }

        $modelNode['collection'] = $collectionClass;
    }

    public function process(ContainerBuilder $container): void
    {
        $models = [];

        // pseudo tags
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!$class || !class_exists($class)) {
                continue;
            }

            // Model Mapper
            if (is_subclass_of($class, ModelMapper::class)) {
                if (!$modelAttribute = $this->getModelClassAttribute($definition->getClass())) {
                    continue;
                }

                $modelClass = (string) $modelAttribute->model;
                $models[$modelClass]['mapper'] = new Reference($id);

                $this->replaceCollectionClass(
                    $models[$modelClass],
                    $modelAttribute->collection
                );
            }

            // Model Factory
            if (is_subclass_of($class, ModelFactory::class)) {
                if (!$modelAttribute = $this->getModelClassAttribute($definition->getClass())) {
                    continue;
                }

                $modelClass = (string) $modelAttribute->model;
                $models[$modelClass]['factory'] = new Reference($id);

                $this->replaceCollectionClass(
                    $models[$modelClass],
                    $modelAttribute->collection
                );
            }

            // Model Store
            if (is_subclass_of($class, ModelStore::class)) {
                if (!$modelAttribute = $this->getModelClassAttribute($definition->getClass())) {
                    continue;
                }

                $modelClass = (string) $modelAttribute->model;
                $models[$modelClass]['store'] = new Reference($id);

                $this->replaceCollectionClass(
                    $models[$modelClass],
                    $modelAttribute->collection
                );
            }

            // Model Middleware
            if (is_subclass_of($class, ModelMiddleware::class)) {
                $ref = new \ReflectionClass($definition->getClass());

                foreach ($ref->getAttributes(MiddlewareAttribute::class) as $attribute) {
                    $middlewareAttribute = $attribute->newInstance();

                    $modelClass = $middlewareAttribute->class;
                    $strategy = $middlewareAttribute->on;

                    $proxyDefinition = new Definition(Middleware::class, [
                        [new Reference($id), $middlewareAttribute->handler],
                        $middlewareAttribute->priority,
                    ]);
                    $proxyDefinition->setPublic(false);

                    $proxyId = $id.'\\'.$strategy->value;
                    $container->setDefinition($proxyId, $proxyDefinition);

                    $scopes = [$strategy];
                    if (Scope::All === $strategy) {
                        $scopes = Scope::cases();
                    }

                    $reference = new Reference($proxyId);
                    foreach ($scopes as $scope) {
                        $models[(string) $modelClass]['middlewares'][$scope->value][] = $reference;
                    }
                }
            }
        }

        $valueResolversFactoryMapping = [];

        foreach ($models as $modelClass => $model) {
            // build factories
            if (isset($model['factory'])) {
                $container->getDefinition($model['factory'])
                    ->setArgument(0, $modelClass)
                    ->setArgument(2, $model['mapper'] ?? null)
                    ->setArgument(3, (string) $model['collection'] ?? null)
                    ->setArgument(4, $model['middlewares'][Scope::Create->value] ?? [])
                    ->setArgument(5, $model['middlewares'][Scope::Fetch->value] ?? [])
                ;

                $valueResolversFactoryMapping[(string) $modelClass] = $model['factory'];
                if (isset($model['collection'])) {
                    $valueResolversFactoryMapping[(string) $model['collection']] = $model['factory'];
                }
            }

            // build stores
            if (isset($model['store'])) {
                $container->getDefinition($model['store'])
                    ->setArgument(0, $modelClass)
                    ->setArgument(1, $model['mapper'] ?? null)
                    ->setArgument(2, (string) $model['collection'] ?? null)
                    ->setArgument(3, $model['middlewares'][Scope::Sync->value] ?? [])
                    ->setArgument(4, $model['middlewares'][Scope::Remove->value] ?? [])
                ;
            }
        }

        // register ValueResolver over ModelFactories
        $container->getDefinition(ModelFactoryValueResolver::class)
            ->setArgument(0, $valueResolversFactoryMapping)
        ;
    }
}
