<?php

namespace Cortex\Bridge\Symfony\Controller;

use Cortex\Component\Model\ModelCollection;
use Cortex\ValueObject\RegisteredClass;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModelFactoryValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly array $factoryMapping,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!RegisteredClass::exists($argument->getType())) {
            return [];
        }

        $parameterType = new RegisteredClass($argument->getType());

        if (!array_key_exists((string) $parameterType, $this->factoryMapping)) {
            return [];
        }

        $factory = $this->factoryMapping[(string) $parameterType];

        $collection = $factory->query()
            ->build($this->formFactory, $request)
            ->getCollection()
        ;
        if ($parameterType->isInstanceOf(ModelCollection::class)) {
            yield $collection;

            return;
        }

        $queryFilters = array_filter(
            $request->query->all() + $request->attributes->all(),
            fn ($key) => $collection->query->filters->has($key),
            ARRAY_FILTER_USE_KEY
        );
        if (empty($queryFilters)) {
            if ($argument->isNullable()) {
                return null;
            }

            throw new NotFoundHttpException(sprintf('No available filter keys found for "%s" model.', $factory->modelClass));
        }

        $model = $collection->first();
        if (!$model && !$argument->isNullable()) {
            throw new NotFoundHttpException(sprintf('No instance of "%s" found from given parameters : "%s" .', $factory->modelClass, implode(', ', array_map(fn ($key, $value) => $key.': '.$value, $collection->query->filters->keys(), $collection->query->filters->values()))));
        }

        yield $model;
    }
}
