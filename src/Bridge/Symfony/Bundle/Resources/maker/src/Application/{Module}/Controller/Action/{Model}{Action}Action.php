<?php

namespace Application\{Module}\Controller\Action;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Domain\{Domain}\Model\{Model};
use Domain\{Domain}\Action\{Model}{Action};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Run {action} action on {Model}s
 */
#[Route(
    path: '/{model}/{uuid}/{action}',
    name: '{model}/{action}',
    methods: ['GET', 'POST'],
    options: ['query_filters' => true],
)]
class {Model}{Action}Action implements ControllerInterface
{
    public function __construct(
        private readonly {Model}{Action}\Handler $handler,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.debug%')]
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

        try {
            /** @var {Model}{Action}\Response $response */
            $response = ($this->handler)(
                new {Model}{Action}\Command(${model})
            );

            $session?->getFlashBag()->add('success', '{model}.{action}.success.message');
        }
        catch ({Model}{Action}\Exception $e) {
            $session?->getFlashBag()->add('error', '{model}.{action}.error.'.$e->getMessage());

            if ($this->debug) {
                throw $e;
            }
        }
        
        return new RedirectResponse(
            $request->headers->get(
                'referer', 
                $this->urlGenerator->generate('{module}/{model}/index')
            )
        );
    }

    private function handleJsonRequest({Model} ${model}, Request $request): array|Response
    {
        /** @var {Model}{Action}\Response $response */
        $response = ($this->handler)(
            new {Model}{Action}\Command(${model})
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
