<?php

namespace Cortex\Component\Security\Action\TokenRevoke;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Security\Factory\TokenFactory;
use Cortex\Component\Security\Persistence\TokenStore;

class Handler implements ActionHandler
{
    public function __construct(
        private readonly TokenFactory $factory,
        private readonly TokenStore $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        $existing = $command->token;

        $revoked = $this->factory->create()
            ->with(account: $existing->account)
            ->with(intention: $existing->intention)
            ->with(tokenHash: $existing->tokenHash)
            ->with(expiresAt: new \DateTimeImmutable())
            ->with(label: $existing->label)
            ->with(scopes: $existing->scopes)
            ->with(createdAt: $existing->createdAt)
            ->with(uuid: $existing->uuid)
            ->build();

        $this->store->sync($revoked);

        return new Response($revoked);
    }
}
