<?php

namespace Application\{Module}\Controller\Action;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Domain\{Domain}\Model\{Model};
use Domain\{Domain}\Action\{Model}Archive;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Run archive action on {Model}s
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
        private readonly bool $debug,
    ) {
    }

    /**
     * Handles Html response
     * Redirects on referer or "{module}/{model}/index" route by default
     */
    private function handleHtmlRequest({Model} ${model}, Request $request): Response
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        /** @var {Model}Archive\Response $response */
        $response = ($this->handler)(
            new {Model}Archive\Command(${model})
        );

        $session?->getFlashBag()->add('success', '{model}.archive.success.message');
        
        return new RedirectResponse(
            $request->headers->get(
                'referer', 
                $this->urlGenerator->generate('{module}/{model}/index')
            )
        );
    }

    private function handleJsonRequest({Model} ${model}, Request $request): array|Response
    {
        /** @var {Model}Archive\Response $response */
        $response = ($this->handler)(
            new {Model}Archive\Command(${model})
        );

        return ['response' => $response];
    }

    public function __invoke({Model} $model, Request $request): array|Response
    {
        $method = sprintf('handle%sRequest', ucfirst($request->attributes->get('_format')));
        if (!method_exists($this, $method)) {
            throw new BadRequestException(sprintf('Unhandled format "%s".', $format));
        }

        return $this->$method($model, $request);
    }
}
