<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}{Action}Action.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?= $Module ?>\Controller\Action<?= $subpath_namespace ?? '' ?>;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Component\Exception\DomainException;
use Domain\<?= $Domain ?>\Action\<?= $Model ?><?= $Action ?>;
use Domain\<?= $Domain ?>\Model\<?= $Model ?>;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Run <?= $action ?> action on <?= $Model ?>s.
 */
#[Route(
    path: '/<?= $model ?>/{uuid}/<?= $action ?>',
    name: '<?= $model ?>/<?= $action ?>',
    methods: ['GET', 'POST'],
    requirements: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'],
    options: ['query_filters' => true],
)]
class <?= $Model ?><?= $Action ?>Action implements ControllerInterface
{
    public function __construct(
        private readonly <?= $Model ?><?= $Action ?>\Handler $handler,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    /**
     * Handles Html response.
     * Redirects on referer or "<?= $module ?>/<?= $model ?>/index" route by default.
     */
    private function handleHtmlRequest(<?= $Model ?> $<?= $model ?>, Request $request): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        try {
            /** @var <?= $Model ?><?= $Action ?>\Response $response */
            $response = ($this->handler)(
                new <?= $Model ?><?= $Action ?>\Command($<?= $model ?>)
            );

            $session?->getFlashBag()->add('success', [
                'title' => '<?= $model ?>.alert.<?= $action ?>.success.title',
                'message' => '<?= $model ?>.alert.<?= $action ?>.success.details',
                'params' => ['model' => (string) $<?= $model ?>],
                'domain' => '<?= $domain ?>',
            ]);
        } catch (DomainException $e) {
            $session?->getFlashBag()->add('error', [
                'title' => '<?= $model ?>.alert.<?= $action ?>.error.title',
                'message' => '<?= $model ?>.alert.<?= $action ?>.error.'.$e->getMessage(),
                'domain' => $e->getDomain(),
            ]);

            if ($this->debug) {
                throw $e;
            }
        }

        return new RedirectResponse(
            $request->headers->get(
                'referer',
                $this->urlGenerator->generate('<?= $module ?>/<?= $model ?>/index')
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function handleJsonRequest(<?= $Model ?> $<?= $model ?>, Request $request): array
    {
        /** @var <?= $Model ?><?= $Action ?>\Response $response */
        $response = ($this->handler)(
            new <?= $Model ?><?= $Action ?>\Command($<?= $model ?>)
        );

        return ['response' => $response];
    }

    /**
     * @return array<string, mixed>|Response
     */
    public function __invoke(<?= $Model ?> $model, Request $request): array|Response
    {
        $format = $request->attributes->get('_format', 'html');
        $method = sprintf('handle%sRequest', ucfirst($format));
        if (!method_exists($this, $method)) {
            throw new BadRequestException(sprintf('Unhandled format "%s".', $format));
        }

        return $this->$method($model, $request);
    }
}
