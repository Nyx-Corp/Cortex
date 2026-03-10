<?php

namespace Cortex\Component\Security\Action\AccountArchive;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Date\DateTimeFactory;
use Cortex\Component\Security\Persistence\AccountStore;

class Handler implements ActionHandler
{
    public function __construct(
        private readonly DateTimeFactory $dateTimeFactory,
        private readonly AccountStore $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        $account = $command->account;

        if ($command->isArchived) {
            $account->archive($this->dateTimeFactory->now());
        } else {
            $account->restore();
        }

        $this->store->sync($account);

        return new Response($account, $account->isArchived() === $command->isArchived);
    }
}
