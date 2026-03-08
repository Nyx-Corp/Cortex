<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}ArchiveAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Archive;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Run archive action on <?php echo $Model; ?>s.
 *
 * Route derived by convention: GET /{<?php echo $model; ?>}/{uuid}/archive → {<?php echo $model; ?>}/archive
 */
class <?php echo $Model; ?>ArchiveAction implements ControllerInterface
{
    public function __construct(
        private readonly <?php echo $Model; ?>Archive\Handler $handler,
        private UrlGeneratorInterface $urlGenerator,
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

        /** @var <?php echo $Model; ?>Archive\Response $response */
        $response = ($this->handler)(
            new <?php echo $Model; ?>Archive\Command($<?php echo $model; ?>)
        );

        $session?->getFlashBag()->add('success', [
            'title' => '<?php echo $model; ?>.archive.success.title',
            'message' => '<?php echo $model; ?>.archive.success.message',
            'params' => ['model' => (string) $<?php echo $model; ?>],
            'domain' => '<?php echo $domain; ?>',
        ]);

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
        /** @var <?php echo $Model; ?>Archive\Response $response */
        $response = ($this->handler)(
            new <?php echo $Model; ?>Archive\Command($<?php echo $model; ?>)
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
