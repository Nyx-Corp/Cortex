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

        // Filter non-archived by default (if model uses Archivable trait)
        // The Gmail-style query parser allows overriding with ?q=archivedAt:true
        // $query->filter(archivedAt: null);

        $query
            ->decorate(
                // Define sortable columns (must match template _th/_td blocks)
                sortables: [/* 'firstname', 'lastname' */],
                filters: fn (FormBuilderInterface $filtersFormBuilder) => $filtersFormBuilder
                    // ->add('firstname', Form\TextType::class)
                    // ->add('lastname', Form\TextType::class)
                    // If model uses Archivable trait, add this filter for ?q=archivedAt:true support:
                    // ->add('archivedAt', Form\CheckboxType::class, ['required' => false, 'label' => 'Archivé'])
            )
        ;

        return [
            'collection' => ${model}s->toArray(),
            'form' => $query->getDecorator(),
            'pager' => $query->pager,
        ];
    }
}
