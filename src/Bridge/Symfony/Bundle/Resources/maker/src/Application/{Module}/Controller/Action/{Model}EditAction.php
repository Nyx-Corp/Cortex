<?php

namespace Application\{Module}\Controller\Action;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Application\{Module}\Form\{Model}EditType;
use Domain\{Domain}\Model\{Model};
use Domain\{Domain}\Action\{Model}Edit;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles {Model} creation/edition form
 *
 */
#[Route(name: '{model}/create', path: '/{model}/create', methods: ['GET', 'POST'])]
#[Route(name: '{model}/edit', path: '/{model}/{uuid}/edit', methods: ['GET', 'POST'])]
class {Model}EditAction implements ControllerInterface
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {}
 
    public function __invoke(Request $request, ?{Model} $model): Response|array
    {
        $isNew = $model === null;
        
        /** @var Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        $form = $this->formFactory->createNamed(
            name: '{model}_edit',
            type: {Model}EditType::class,
            data: $model,
            options: [
                'method' => 'POST',
            ]
        );

        try {    
            $form->handleRequest($request);
            
            if ($form->isSubmitted()) {
                if (!$form->isValid()) {
                    $session?->getFlashBag()->add('error', '{model}.error.validation_failed');
                }
                else {

                    if ($isNew) {
                        $session?->getFlashBag()->add(
                            'success', 
                            '{model}.create.success.message'
                        );

                        return new RedirectResponse($this->urlGenerator->generate(
                            name: '{module}/{model}/edit',
                            parameters: ['uuid' => $form->getData()->{model}->uuid]
                        ));
                    }
                    
                    $session?->getFlashBag()->add(
                        'success', 
                        '{model}.edit.success.message'
                    );
                }
            }
        }
        catch ({Model}Edit\Exception $th) {
            $session?->getFlashBag()->add('error', '{model}.error.'.$th->getMessage());

            if ($this->debug) {
                throw $th;
            }
        }

        return [
            '{model}' => $model,
            'form' => $form->createView(),
        ];
    }
}
