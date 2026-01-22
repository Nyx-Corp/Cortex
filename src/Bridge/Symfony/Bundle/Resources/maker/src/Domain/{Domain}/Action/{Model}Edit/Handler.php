<?php

namespace Domain\{Domain}\Action\{Model}Edit;

use Cortex\Component\Action\ActionHandler;
use Domain\{Domain}\Persistence\{Model}Store;
use Domain\{Domain}\Factory\{Model}Factory;

class Handler implements ActionHandler
{
    public function __construct(
        private readonly {Model}Factory $factory,
        private readonly {Model}Store $store,
    ) {
    }

    /**
     * Handles {Model} edition through factory as a new instance.
     * If inner model has inner state, consider not using this handler, make one by state transition.
     */
    public function __invoke(Command $command): Response
    {
        $model = $this->factory->create()
            ->with(...get_object_vars($command))
            ->build()
        ;
        
        $this->store->sync($model);

        return new Response($model);
    }
}
