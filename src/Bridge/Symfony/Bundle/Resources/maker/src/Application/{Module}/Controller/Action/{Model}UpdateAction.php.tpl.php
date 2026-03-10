<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}UpdateAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Application\<?php echo $Module; ?>\Form\<?php echo $Model; ?>UpdateType;
use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles <?php echo $Model; ?> update form.
 *
 * Route derived by convention: GET|POST /{<?php echo $model; ?>}/{uuid}/edit → {<?php echo $model; ?>}/edit
 */
class <?php echo $Model; ?>UpdateAction implements ControllerInterface
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
    public function __invoke(Request $request, <?php echo $Model; ?> $model): Response|array
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        $form = $this->formFactory->createNamed(
            name: '<?php echo $model; ?>_update',
            type: <?php echo $Model; ?>UpdateType::class,
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
