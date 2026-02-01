<?php

namespace Application\{Module}\Controller\Action;

use Application\{Module}\Form\{Model}EditType;
use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\{Domain}\Model\{Model};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles {Model} creation/edition form.
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
    ) {
    }

    /**
     * @return array<string, mixed>|Response
     */
    public function __invoke(Request $request, ?{Model} $model): Response|array
    {
        $isNew = null === $model;

        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
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
                    $session?->getFlashBag()->add('error', [
                        'title' => '{model}.alert.edit.error.title',
                        'message' => '{model}.alert.edit.error.details',
                        'domain' => '{domain}',
                    ]);
                } else {
                    if ($isNew) {
                        $session?->getFlashBag()->add('success', [
                            'title' => '{model}.alert.create.success.title',
                            'message' => '{model}.alert.create.success.details',
                            'params' => ['model' => (string) $form->getData()->{model}],
                            'domain' => '{domain}',
                        ]);

                        return new RedirectResponse($this->urlGenerator->generate(
                            name: '{module}/{model}/edit',
                            parameters: ['uuid' => $form->getData()->{model}->uuid]
                        ));
                    }

                    $session?->getFlashBag()->add('success', [
                        'title' => '{model}.alert.edit.success.title',
                        'message' => '{model}.alert.edit.success.details',
                        'params' => ['model' => (string) $model],
                        'domain' => '{domain}',
                    ]);
                }
            }
        } catch (DomainException $th) {
            $session?->getFlashBag()->add('error', [
                'title' => '{model}.alert.error.title',
                'message' => '{model}.alert.error.'.$th->getMessage(),
                'domain' => $th->getDomain(),
            ]);

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
