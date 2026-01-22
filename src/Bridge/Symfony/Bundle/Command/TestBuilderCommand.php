<?php

namespace Cortex\Bridge\Symfony\Bundle\Command;

use Cortex\ValueObject\Email;
use Domain\Account\Factory\AccountFactory;
use Domain\Account\Persistence\AccountStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

class TestBuilderCommand extends Command
{
    public function __construct(
        private AccountFactory $accountFactory,
        private AccountStore $accountStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('cortex:test-builder')
            ->setDescription('')
            ->setHelp('')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $account = $this->accountFactory->create()
            ->with(uuid: Uuid::v7())
            ->with(username: new Email('quentin2@nyx-corp-agency.com'))
            ->with(acl: ['ROLE_ADMIN', 'ROLE_USER', 'ROLE_TA_MERE'])
            ->build();

        $persistResult = $this->accountStore->sync($account);
        dump($persistResult);

        $account = $this->accountFactory->fetch()
            ->where(username: new Email('quentin2@nyx-corp-agency.com'))
            ->first();

        dump($account);

        return 0;
    }
}
