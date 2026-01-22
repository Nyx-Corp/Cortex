<?php

namespace Cortex\Bridge\Symfony\Collection;

use Cortex\Bridge\Symfony\Form\CollectionDecoratorType;
use Cortex\Component\Collection\AsyncCollection;
use Cortex\Component\Model\Factory\Builder\FetchBuilder;
use Cortex\Component\Model\Decorator\Pager;
use Cortex\Component\Model\Decorator\SortDirection;
use Cortex\Component\Model\Decorator\Sorter;
use Cortex\ValueObject\RegisteredClass;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class CollectionDecoratorBuilder
{
    public const PAGE_KEY = '_pager_page';
    public const PAGE_SIZE_KEY = '_pager_size';
    public const SORT_KEY = '_sort';

    private array $decoratorPool = [];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function create(AsyncCollection $collection): CollectionDecorator
    {
        $decorators = array_filter(
            $this->decoratorPool,
            fn (CollectionDecorator $decorator) => $decorator->collection === $collection
        );

        if (empty($decorators)) {
            throw new \LogicException('Decorator have to be initialized with initFromRequest() first.');
        }

        // for proper using of decorator, AsyncCollection must be initialized
        $collection->first();

        return $decorators[0];
    }

    public function prototype(FetchBuilder $builder, array $filtersFormOptions = []): CollectionDecorator
    {
        return new CollectionDecorator(
            collection: $builder->all(),
            formBuilder: $this->formFactory->createNamedBuilder(
                '',   // flat fields for readable urls
                CollectionDecoratorType::class,
                $builder->filters->all() + [
                    '_pager' => $pager ?? [],
                    '_sort' => $sorter ?? [],
                ],
                $filtersFormOptions
            )
        );
    }

    public function prototypeFromRequest(Request $request, RegisteredClass $modelClass, FetchBuilder $builder): CollectionDecorator
    {
        $route = $this->router->getRouteCollection()->get($request->attributes->get('_route'));
        if (!$route) {
            throw new \RuntimeException(sprintf('Route "%s" not found.', $request->attributes->get('_route')));
        }

        if ($defaultSorting = $route->getOption('sorting')) {
            [$field, $direction] = preg_match(
                '/^(?P<field>.+?)(?:_(?P<dir>asc|desc))?$/',
                $request->query->get(self::SORT_KEY, is_bool($defaultSorting) ? '' : $defaultSorting),
                $m
            ) ? [$m['field'], $m['dir'] ?? ''] : [null, null]
            ;

            if ($field && $builder->filters->has($field)) {
                $builder->sort($sorter = new Sorter(
                    $field,
                    SortDirection::fromString($direction)
                ));
            }
        }

        if ($route->getOption('pagination')) {
            $builder->paginate($pager = new Pager(
                (int) $request->query->get(self::PAGE_KEY, 1),
                (int) $request->query->get(self::PAGE_SIZE_KEY, 25)
            ));
        }

        $filters = AsyncCollection::create($request->attributes->all() + ($route->getOption('query_filters') ? $request->query->all() : []))
            ->filter(fn ($value, string $key) => !str_starts_with($key, '_') && (
                $builder->filters->has($key) || $route->getOption('extra_filters') ?? false
            ))
        ;

        $decorator = new CollectionDecorator(
            collection: $filters
                ->reduce(fn ($builder, $value, string $key) => $builder->filterBy($key, $value), $builder)
                ->all(),
            formBuilder: $this->formFactory->createNamedBuilder(
                '',   // flat fields for readable urls
                CollectionDecoratorType::class,
                $filters->toArray() + [
                    '_pager' => $pager ?? [],
                    '_sort' => $sorter ?? [],
                ],
                [
                    'model_name' => strtolower(preg_filter('#^.*\\\\#', '', $modelClass->value)),
                    'module_name' => $route->getOption('module') ?? null,
                    'csrf_protection' => false,
                ]
            )
        );

        $this->decoratorPool[] = $decorator;

        return $decorator;
    }
}
