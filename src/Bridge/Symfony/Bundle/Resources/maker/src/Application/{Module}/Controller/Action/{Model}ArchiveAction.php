<?php

namespace Application\{Module}\Controller\Action;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Domain\{Domain}\Action\{Model}Archive;
use Domain\{Domain}\Model\{Model};
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Run archive action on {Model}s.
 */
#[Route(
    path: '/{model}/{uuid}/archive',
    name: '{model}/archive',
    methods: ['GET'],
    options: ['query_filters' => true],
)]
class {Model}ArchiveAction implements ControllerInterface
{
    public function __construct(
        private readonly {Model}Archive\Handler $handler,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Handles Html response.
     * Redirects on referer or "{module}/{model}/index" route by default.
     */
    private function handleHtmlRequest({Model} ${model}, Request $request): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session|null $session */
        $session = $request->hasSession() ? $request->getSession() : null;

        /** @var {Model}Archive\Response $response */
        $response = ($this->handler)(
            new {Model}Archive\Command(${model})
        );

        $session?->getFlashBag()->add('success', [
            'title' => '{model}.alert.archive.success.title',
            'message' => '{model}.alert.archive.success.details',
            'params' => ['model' => (string) ${model}],
            'domain' => '{domain}',
        ]);

        return new RedirectResponse(
            $request->headers->get(
                'referer',
                $this->urlGenerator->generate('{module}/{model}/index')
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function handleJsonRequest({Model} ${model}, Request $request): array
    {
        /** @var {Model}Archive\Response $response */
        $response = ($this->handler)(
            new {Model}Archive\Command(${model})
        );

        return ['response' => $response];
    }

    /**
     * @return array<string, mixed>|Response
     */
    public function __invoke({Model} $model, Request $request): array|Response
    {
        $format = $request->attributes->get('_format', 'html');
        $method = sprintf('handle%sRequest', ucfirst($format));
        if (!method_exists($this, $method)) {
            throw new BadRequestException(sprintf('Unhandled format "%s".', $format));
        }

        return $this->$method($model, $request);
    }
}
