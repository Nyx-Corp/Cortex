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

final class CommandMaker extends CortexMaker
{
    public static function getCommandName(): string
    {
        return 'make:cortex:command';
    }

    public static function getCommandDescription(): string
    {
        return 'Crée une commande Symfony dans la structure à module.';
    }

    public static function getGeneratedPaths(): array
    {
        return [
            'src/Application/{Module}/Command/{Command}Command.php',
        ];
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('module', InputArgument::REQUIRED, 'Module name')
            ->addArgument('_command', InputArgument::REQUIRED, 'Snake case command name (ex: model:do_stuff)')   // "command" is reserved
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $moduleUnicode = u($input->getArgument('module'));
        $commandUnicode = u($input->getArgument('_command'));

        $this->pathCollection
            ->filter(fn (SplFileInfo $file) => in_array(
                $file->getRelativePathname(),
                self::getGeneratedPaths()
            ))
            ->mirror(
                $generator->getRootDirectory(),
                [
                    '{module}' => $module = $moduleUnicode->snake(),
                    '{Module}' => $Module = $moduleUnicode->camel()->title(),
                    '{Command}' => $Command = $commandUnicode->replace(':', '_')->camel()->title(),
                    '{command}' => $commandUnicode->lower(),
                ],
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success("[Cortex] Command '{$Module}'/'{$Command}' créé avec succès.");
    }
}
