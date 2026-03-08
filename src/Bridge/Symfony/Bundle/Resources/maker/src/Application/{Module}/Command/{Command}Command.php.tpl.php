<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Command/{Command}Command.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @example php bin/console <?php echo $module; ?>:<?php echo $command; ?> --help
 */
class <?php echo $Command; ?>Command extends Command
{
    public function __construct(
        // private readonly .....Handler $handler,
    ) {
        parent::__construct();
    }

    /**
     * @example php bin/console <?php echo $module; ?>:<?php echo $command; ?> [req_arg] [opt_arg?] [pos_arg_1] [pos_arg_2] -f -p /tmp
     * @example
     *     $this // .....
     *       ->addArgument('req_arg', InputArgument::REQUIRED, 'Description')
     *       ->addArgument('opt_arg', InputArgument::OPTIONAL, 'Description')
     *       ->addArgument('pos_arg', InputArgument::IS_ARRAY, 'Description')
     *
     *       ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation');
     *       ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Path to the target file', 'default/path/to/file')
     *     ;
     */
    protected function configure(): void
    {
        $this
            ->setName('<?php echo $module; ?>:<?php echo $command; ?>')
            // ->setDescription('')
            // ->setHelp('This command allows you to ...')
        ;
    }

    /**
     * @example
     *      $arg = $input->getArgument('req_arg');
     *      $opt = $input->getOption('req_arg');
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        dump($input->getArguments());
        dump($input->getOptions());

        return 0;
    }
}
