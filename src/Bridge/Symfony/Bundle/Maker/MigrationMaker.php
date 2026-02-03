<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\SplFileInfo;

use function Symfony\Component\String\u;

final class MigrationMaker extends CortexMaker
{
    public static function getCommandName(): string
    {
        return 'make:cortex:migration';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a migration just with Dbal logic.';
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    public static function getGeneratedPaths(array $options): array
    {
        return [
            'migrations/Version{datetime}.php.tpl.php',
        ];
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('table', InputArgument::REQUIRED, 'Table to create / alter.')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $this->pathCollection
            ->filter(fn (SplFileInfo $file) => in_array(
                $file->getRelativePathname(),
                self::getGeneratedPaths($input->getOptions())
            ))
            ->mirror(
                $generator->getRootDirectory(),
                ['{table}' => u($input->getArgument('table'))->snake()->toString()],
                ['{datetime}' => [date('YmdHis')]],
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success('[Cortex] Migration created.');
    }
}
