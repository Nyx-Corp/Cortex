<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}{Action}FormAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?= $Module ?>\Controller\Action<?= $subpath_namespace ?? '' ?>;

use Application\<?= $Module ?>\Form\<?= $Model ?><?= $Action ?>Type;
use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\<?= $Domain ?>\Action\<?= $Model ?><?= $Action ?>;
use Domain\<?= $Domain ?>\Model\<?= $Model ?>;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles "<?= $Action ?>" action for module <?= $module ?>.
 *
 * @see Domain\<?= $Domain ?>\Action\<?= $Action ?>\Handler
 */
class <?= $Model ?><?= $Action ?>FormAction implements ControllerInterface
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
        path: '/<?= $model ?>/<?= $action ?>/{uuid}',
        name: '<?= $model ?>/<?= $action ?>',
        methods: ['GET', 'POST'],
        requirements: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    )]
    public function __invoke(Request $request, <?= $Model ?> $<?= $model ?>): Response|array
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        $form = $this->formFactory->createNamed(
            name: '<?= $_action ?? $action ?>',
            type: <?= $Model ?><?= $Action ?>Type::class,
            data: [
                // 'label' => $<?= $model ?>->label,
            ],
            options: [
                'data_class' => <?= $Action ?>\Command::class,
                'action' => $this->urlGenerator->generate(
                    route: '<?= $module ?>/<?= $model ?>/<?= $action ?>',
                    parameters: ['uuid' => $<?= $model ?>->uuid]
                ),
                'method' => 'POST',
            ]
        );

        try {
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                if (!$form->isValid()) {
                    $session?->getFlashBag()->add('error', [
                        'message' => '<?= $action ?>.error.validation_failed',
                        'domain' => '<?= $module ?>',
                    ]);
                } else {
                    $session?->getFlashBag()->add('success', [
                        'message' => '<?= $action ?>.success',
                        'domain' => '<?= $module ?>',
                    ]);

                    /** @var Domain\<?= $Domain ?>\Action\<?= $Action ?>\Response $response */
                    $response = $form->getData();

                    // do stuff here

                    // return new RedirectResponse($this->urlGenerator->generate(
                    //     route: '<?= $module ?>/<?= $model ?>/....',
                    //     parameters: ['uuid' => $response-><?= $model ?>->uuid]
                    // ));
                }
            }
        } catch (DomainException $th) {
            $session?->getFlashBag()->add('error', [
                'message' => '<?= $action ?>.error.'.$th->getMessage(),
                'domain' => $th->getDomain(),
            ]);

            if ($this->debug) {
                throw $th;
            }
        }

        return [
            '<?= $model ?>' => $model,
            'form' => $form->createView(),
        ];
    }
}
