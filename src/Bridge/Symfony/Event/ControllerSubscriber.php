<?php

namespace Cortex\Bridge\Symfony\Event;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TemplatingEngine;

class ControllerSubscriber implements EventSubscriberInterface
{
    /**
     * Fallback template format if undefined in routes.
     */
    public const FALLBACK_FORMAT = 'html';

    /**
     * @inherit_doc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::VIEW => 'onControllerView',
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function __construct(
        protected RouterInterface $router,
        protected TemplatingEngine $templateEngine,
        protected LoggerInterface $logger,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->has('_format')) {
            return;
        }

        $format = $request->getFormat($request->getMimeType($request->getPreferredFormat()));

        $this->logger->debug('Setting request format based on Accept header', [
            'format' => $format,
            'preferred_format' => $request->getPreferredFormat(),
            'mime_type' => $request->getMimeType($request->getPreferredFormat()),
        ]);

        $request->attributes->set('_format', $format ?: self::FALLBACK_FORMAT);
    }

    public function onControllerView(ViewEvent $event)
    {
        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');
        $format = $request->attributes->get('_format', self::FALLBACK_FORMAT);

        $route = $this->router->getRouteCollection()->get($routeName);
        $template = null;
        $module = null;

        if ($route instanceof Route) {
            $template = $route->getOption('template') ?? $template;
            $module = $route->getOption('module') ?? $request->attributes->get('module');
        }

        // fallback sur le nom de route si rien
        $template ??= $routeName;

        $templateChain = [
            $template,
            sprintf('%s.%s.twig', $template, $format),
        ];

        if ($module) {
            $templateChain[] = sprintf('%s/%s', $module, $template);
            $templateChain[] = sprintf('%s/%s.%s.twig', $module, $template, $format);
        }

        foreach ($templateChain as $templatePath) {
            if ($templatePath && $this->templateEngine->getLoader()->exists($templatePath)) {
                $event->setResponse(
                    new Response($this->templateEngine->render(
                        $templatePath,
                        $event->getControllerResult()
                    ))
                );

                return;
            }
        }

        // json ?
        if ('json' === $format) {
            $event->setResponse(new JsonResponse(
                $result = $event->getControllerResult(),
                empty($result) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK,
            ));

            return;
        }

        $this->logger->warning('No template guessed from routing while no Response returned from controller.', [
            'tried_templates' => $templateChain,
        ]);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ('json' !== $request->getRequestFormat()) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500
        ;

        $event->setResponse(new JsonResponse(
            ['error' => $exception->getMessage()],
            $status
        ));
    }
}
