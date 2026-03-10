<?php

namespace Cortex\Component\Security\Action\TokenCreate;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Security\Factory\TokenFactory;
use Cortex\Component\Security\Persistence\TokenStore;
use Cortex\Component\Security\Service\TokenHasher;

class Handler implements ActionHandler
{
    public function __construct(
        private readonly TokenFactory $factory,
        private readonly TokenStore $store,
        private readonly TokenHasher $tokenHasher,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        $tokenData = $this->tokenHasher->generate();

        $token = $this->factory->create()
            ->with(account: $command->account)
            ->with(intention: $command->intention)
            ->with(tokenHash: $tokenData['tokenHash'])
            ->with(expiresAt: new \DateTimeImmutable($command->expiresIn))
            ->with(label: $command->label)
            ->with(scopes: $command->scopes)
            ->with(createdAt: new \DateTimeImmutable())
            ->build();

        $this->store->sync($token);

        return new Response($token, rawToken: $tokenData['token']);
    }
}
