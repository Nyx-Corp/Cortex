<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}{Action}Action.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?><?php echo $Action; ?>;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Run <?php echo $action; ?> action on <?php echo $Model; ?>s.
 */
#[Route(
    path: '/<?php echo $model; ?>/{uuid}/<?php echo $action; ?>',
    name: '<?php echo $model; ?>/<?php echo $action; ?>',
    methods: ['GET', 'POST'],
    requirements: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    options: ['query_filters' => true],
)]
class <?php echo $Model; ?><?php echo $Action; ?>Action implements ControllerInterface
{
    public function __construct(
        private readonly <?php echo $Model; ?><?php echo $Action; ?>\Handler $handler,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    /**
     * Handles Html response.
     * Redirects on referer or "<?php echo $module; ?>/<?php echo $model; ?>/index" route by default.
     */
    private function handleHtmlRequest(<?php echo $Model; ?> $<?php echo $model; ?>, Request $request): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        try {
            /** @var <?php echo $Model; ?><?php echo $Action; ?>\Response $response */
            $response = ($this->handler)(
                new <?php echo $Model; ?><?php echo $Action; ?>\Command($<?php echo $model; ?>)
            );

            $session?->getFlashBag()->add('success', [
                'title' => '<?php echo $model; ?>.<?php echo $action; ?>.success.title',
                'message' => '<?php echo $model; ?>.<?php echo $action; ?>.success.message',
                'params' => ['model' => (string) $<?php echo $model; ?>],
                'domain' => '<?php echo $domain; ?>',
            ]);
        } catch (DomainException $e) {
            $session?->getFlashBag()->add('error', [
                'title' => '<?php echo $model; ?>.error.'.$e->getMessage(),
                'domain' => $e->getDomain(),
            ]);

            if ($this->debug) {
                throw $e;
            }
        }

        return new RedirectResponse(
            $request->headers->get(
                'referer',
                $this->urlGenerator->generate('<?php echo $module; ?>/<?php echo $model; ?>/index')
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function handleJsonRequest(<?php echo $Model; ?> $<?php echo $model; ?>, Request $request): array
    {
        /** @var <?php echo $Model; ?><?php echo $Action; ?>\Response $response */
        $response = ($this->handler)(
            new <?php echo $Model; ?><?php echo $Action; ?>\Command($<?php echo $model; ?>)
        );

        return ['response' => $response];
    }

    /**
     * @return array<string, mixed>|Response
     */
    public function __invoke(<?php echo $Model; ?> $model, Request $request): array|Response
    {
        $format = $request->attributes->get('_format', 'html');
        $method = sprintf('handle%sRequest', ucfirst($format));
        if (!method_exists($this, $method)) {
            throw new BadRequestException(sprintf('Unhandled format "%s".', $format));
        }

        return $this->$method($model, $request);
    }
}
