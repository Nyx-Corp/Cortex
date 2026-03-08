<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}EditAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Application\<?php echo $Module; ?>\Form\<?php echo $Model; ?>EditType;
use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles <?php echo $Model; ?> creation/edition form.
 */
#[Route(name: '<?php echo $model; ?>/create', path: '/<?php echo $model; ?>/create', methods: ['GET', 'POST'])]
#[Route(name: '<?php echo $model; ?>/edit', path: '/<?php echo $model; ?>/{uuid}/edit', methods: ['GET', 'POST'], requirements: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
class <?php echo $Model; ?>EditAction implements ControllerInterface
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
    public function __invoke(Request $request, ?<?php echo $Model; ?> $model): Response|array
    {
        $isNew = null === $model;

        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        $form = $this->formFactory->createNamed(
            name: '<?php echo $model; ?>_edit',
            type: <?php echo $Model; ?>EditType::class,
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
                        'title' => '<?php echo $model; ?>.error.validation_failed',
                        'domain' => '<?php echo $domain; ?>',
                    ]);
                } else {
                    if ($isNew) {
                        $<?php echo $model; ?> = $form->getData()-><?php echo $model; ?>;
                        $session?->getFlashBag()->add('success', [
                            'title' => '<?php echo $model; ?>.create.success.title',
                            'message' => '<?php echo $model; ?>.create.success.message',
                            'params' => ['model' => (string) $<?php echo $model; ?>],
                            'domain' => '<?php echo $domain; ?>',
                        ]);

                        return new RedirectResponse($this->urlGenerator->generate(
                            name: '<?php echo $module; ?>/<?php echo $model; ?>/edit',
                            parameters: ['uuid' => $<?php echo $model; ?>->uuid]
                        ));
                    }

                    $session?->getFlashBag()->add('success', [
                        'title' => '<?php echo $model; ?>.edit.success.title',
                        'message' => '<?php echo $model; ?>.edit.success.message',
                        'params' => ['model' => (string) $model],
                        'domain' => '<?php echo $domain; ?>',
                    ]);
                }
            }
        } catch (DomainException $th) {
            $session?->getFlashBag()->add('error', [
                'title' => '<?php echo $model; ?>.error.'.$th->getMessage(),
                'domain' => $th->getDomain(),
            ]);

            if ($this->debug) {
                throw $th;
            }
        }

        return [
            '<?php echo $model; ?>' => $model,
            'form' => $form->createView(),
        ];
    }
}
