<?php

namespace Domain\{Domain}\Action\{Model}Archive;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Date\DateTimeFactory;
use Domain\{Domain}\Persistence\{Model}Store;

class Handler implements ActionHandler
{
    public function __construct(
        private DateTimeFactory $dateTimeFactory,
        private {Model}Store $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        /** @var \Domain\{Domain}\Model\{Model} $model */
        $model = $command->{model};
        
        if ($command->isArchived) {
            $model->archive($this->dateTimeFactory->now());
        } else {
            $model->restore();
        }

        $this->store->sync($model);

        return new Response(
            $model,
            $model->isArchived() == $command->isArchived
        );
    }
}
