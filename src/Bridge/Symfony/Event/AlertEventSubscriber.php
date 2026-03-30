<?php

namespace Cortex\Bridge\Symfony\Event;

use Cortex\Component\Event\ModelEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AlertEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelEvent::class => 'onModelEvent',
        ];
    }

    public function onModelEvent(ModelEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $eventClass = $event::class;

        // Parse domain and action from event namespace
        // Convention: Domain\{Domain}\Action\{ModelAction}\Event
        if (!preg_match('/^Domain\\\\(\w+)\\\\Action\\\\(\w+)\\\\Event$/', $eventClass, $matches)) {
            return;
        }

        $domain = strtolower($matches[1]);
        $modelAction = $matches[2];

        // Convert ModelAction to model.action format
        $parts = preg_split('/(?=[A-Z])/', $modelAction, -1, PREG_SPLIT_NO_EMPTY);
        $key = strtolower(implode('.', $parts));

        $session->getFlashBag()->add('success', [
            'title' => $key.'.success.title',
            'domain' => $domain,
        ]);
    }
}
