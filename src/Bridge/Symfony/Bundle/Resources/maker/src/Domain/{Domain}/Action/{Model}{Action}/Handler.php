<?php

namespace Domain\{Domain}\Action\{Model}{Action};

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Event\EmitsActionEvents;
use Cortex\Component\Event\EventDispatcherAwareInterface;
use Domain\{Domain}\Persistence\{Model}Store;

class Handler implements ActionHandler, EventDispatcherAwareInterface
{
    use EmitsActionEvents;

    public function __construct(
        private {Model}Store $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        /** @var $model Domain\{Domain}\Model\{Model} */
        $model = $command->{model};
        
        // do things

        $this->store->sync($model);

        $this->emit($event = new Event(new Response($model)));

        return $event->getResponse();
    }
}
