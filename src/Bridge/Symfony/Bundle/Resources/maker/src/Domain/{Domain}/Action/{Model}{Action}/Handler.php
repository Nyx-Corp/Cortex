<?php

namespace Domain\{Domain}\Action\{Model}{Action};

use Cortex\Component\Action\ActionHandler;
use Domain\{Domain}\Persistence\{Model}Store;

class Handler implements ActionHandler
{
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

        return new Response($model);
    }
}
