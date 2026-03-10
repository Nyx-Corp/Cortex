<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}{Action}FormAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Application\<?php echo $Module; ?>\Form\<?php echo $Model; ?><?php echo $Action; ?>Type;
use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?><?php echo $Action; ?>;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles "<?php echo $Action; ?>" action for module <?php echo $module; ?>.
 *
 * @see Domain\<?php echo $Domain; ?>\Action\<?php echo $Action; ?>\Handler
 */
class <?php echo $Model; ?><?php echo $Action; ?>FormAction implements ControllerInterface
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
    #[Route(
        path: '/<?php echo $model; ?>/<?php echo $action; ?>/{uuid}',
        name: '<?php echo $model; ?>/<?php echo $action; ?>',
        methods: ['GET', 'POST'],
        requirements: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    )]
    public function __invoke(Request $request, <?php echo $Model; ?> $<?php echo $model; ?>): Response|array
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        $form = $this->formFactory->createNamed(
            name: '<?php echo $_action ?? $action; ?>',
            type: <?php echo $Model; ?><?php echo $Action; ?>Type::class,
            data: [
                // 'label' => $<?php echo $model; ?>->label,
            ],
            options: [
                'data_class' => <?php echo $Action; ?>\Command::class,
                'action' => $this->urlGenerator->generate(
                    route: '<?php echo $module; ?>/<?php echo $model; ?>/<?php echo $action; ?>',
                    parameters: ['uuid' => $<?php echo $model; ?>->uuid]
                ),
                'method' => 'POST',
            ]
        );

        try {
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                if (!$form->isValid()) {
                    $session?->getFlashBag()->add('error', [
                        'title' => '<?php echo $action; ?>.error.validation_failed',
                        'domain' => '<?php echo $module; ?>',
                    ]);
                } else {
                    $session?->getFlashBag()->add('success', [
                        'title' => '<?php echo $action; ?>.success.title',
                        'message' => '<?php echo $action; ?>.success.message',
                        'params' => ['model' => (string) $<?php echo $model; ?>],
                        'domain' => '<?php echo $module; ?>',
                    ]);

                    /** @var Domain\<?php echo $Domain; ?>\Action\<?php echo $Action; ?>\Response $response */
                    $response = $form->getData();

                    // do stuff here

                    // return new RedirectResponse($this->urlGenerator->generate(
                    //     route: '<?php echo $module; ?>/<?php echo $model; ?>/....',
                    //     parameters: ['uuid' => $response-><?php echo $model; ?>->uuid]
                    // ));
                }
            }
        } catch (DomainException $th) {
            $session?->getFlashBag()->add('error', [
                'title' => '<?php echo $action; ?>.error.'.$th->getMessage(),
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
