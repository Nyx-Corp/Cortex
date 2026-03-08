<?php

namespace Cortex\Component\Security\Action\AskPasswordReset;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Security\Factory\AccountFactory;
use Cortex\Component\Security\Factory\TokenFactory;
use Cortex\Component\Security\Persistence\TokenStore;
use Cortex\Component\Security\Service\TokenHasher;

class Handler implements ActionHandler
{
    public function __construct(
        private readonly AccountFactory $accountFactory,
        private readonly TokenFactory $tokenFactory,
        private readonly TokenStore $tokenStore,
        private readonly TokenHasher $tokenHasher,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        $account = $this->accountFactory->query()
            ->filter(username: $command->email)
            ->getCollection()
            ->first();

        if (!$account) {
            // Never reveal whether the email exists
            return new Response(null, null);
        }

        $tokenData = $this->tokenHasher->generate();

        $token = $this->tokenFactory->create()
            ->with(account: $account)
            ->with(intention: 'reset_password')
            ->with(tokenHash: $tokenData['tokenHash'])
            ->with(expiresAt: new \DateTimeImmutable('+1 hour'))
            ->with(createdAt: new \DateTimeImmutable())
            ->build();

        $this->tokenStore->sync($token);

        return new Response($token, rawToken: $tokenData['token']);
    }
}
