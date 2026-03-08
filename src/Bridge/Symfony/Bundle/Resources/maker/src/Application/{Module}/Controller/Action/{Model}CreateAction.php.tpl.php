<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}CreateAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Application\<?php echo $Module; ?>\Form\<?php echo $Model; ?>CreateType;
use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles <?php echo $Model; ?> creation form.
 *
 * Route derived by convention: GET|POST /{<?php echo $model; ?>}/create → {<?php echo $model; ?>}/create
 */
class <?php echo $Model; ?>CreateAction implements ControllerInterface
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
    public function __invoke(Request $request): Response|array
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        $form = $this->formFactory->createNamed(
            name: '<?php echo $model; ?>_create',
            type: <?php echo $Model; ?>CreateType::class,
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
            '<?php echo $model; ?>' => null,
            'form' => $form->createView(),
        ];
    }
}
