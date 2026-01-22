<?php

namespace Application\{Module}\Controller\Action;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Domain\{Domain}\Factory\{Model}Factory;
use Domain\{Domain}\Model\{Model}Collection;
use Symfony\Component\Form\Extension\Core\Type as Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Displays {Model}s as a list using Cortex decorator for sorting, filtering, and pagination.
 */
#[Route(
    path: '/{model}s',
    name: '{model}/index',
    methods: ['GET'],
)]
class {Model}ListAction implements ControllerInterface
{
    public function __construct(
        private readonly {Model}Factory ${model}Factory,
    ) {
    }

    public function __invoke({Model}Collection ${model}s): array|Response
    {
        /** @var ModelQueryDecorator $query */
        $query = ${model}s->query;

        // Filter non-archived by default (uncomment if model has archivedAt field)
        // $query->filter(archivedAt: null);

        $query
            ->decorate(
                // Define sortable columns (must match template _th/_td blocks)
                sortables: [/* 'firstname', 'lastname' */],
                filters: fn (FormBuilderInterface $filtersFormBuilder) => $filtersFormBuilder
                    // ->add('firstname', Form\TextType::class)
                    // ->add('lastname', Form\TextType::class)
                    // ->add('archived', Form\CheckboxType::class, ['required' => false])
            )
        ;

        return [
            'collection' => ${model}s->toArray(),
            'form' => $query->getDecorator(),
        ];
    }
}
