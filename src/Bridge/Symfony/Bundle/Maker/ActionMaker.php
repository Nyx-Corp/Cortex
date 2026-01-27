<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\SplFileInfo;

use function Symfony\Component\String\u;

final class ActionMaker extends CortexMaker
{
    public static function getCommandName(): string
    {
        return 'make:cortex:action';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new Cortex action';
    }

    public static function getGeneratedPaths(array $options): array
    {
        $paths = [
            'src/Domain/{Domain}/Error/{Model}Exception.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Command.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Exception.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Handler.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Response.php',
        ];

        // MCP Tool
        if ($options['mcp-tool'] ?? false) {
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}{Action}Tool.php';
        }

        return array_merge($paths, match ($options['controller']) {
            'form' => [
                'src/Application/{Module}/Form/{Model}{Action}Type.php',
                'src/Application/{Module}/Controller/Action/{Model}{Action}FormAction.php',
            ],
            'list' => [
                'src/Application/{Module}/Controller/Action/{Model}ListAction.php',
            ],
            'model' => [
                'src/Application/{Module}/Controller/Action/{Model}{Action}Action.php',
            ],
            default => [],
        });
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('module', InputArgument::REQUIRED, 'Action related module')
            ->addArgument('domain', InputArgument::REQUIRED, 'Action related domain')
            ->addArgument('model', InputArgument::REQUIRED, 'Action related model')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to generate')
            ->addOption('controller', 'null', InputOption::VALUE_REQUIRED, 'Controller (model|form|list)', 'model')
            ->addOption('mcp-tool', null, InputOption::VALUE_NONE, 'Generate MCP Tool wrapper')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $moduleUnicode = u($input->getArgument('module'));
        $domainUnicode = u($input->getArgument('domain'));
        $modelUnicode = u($input->getArgument('model'));
        $actionUnicode = u($input->getArgument('action'));

        $sourcePath = self::getGeneratedPaths($input->getOptions());

        $this->pathCollection
            ->filter(fn (SplFileInfo $file) => in_array(
                $file->getRelativePathname(),
                $sourcePath,
            ))
            ->mirror(
                $generator->getRootDirectory(),
                [
                    '{model}' => $modelUnicode->snake()->toString(),
                    '{Model}' => $Model = $modelUnicode->camel()->title()->toString(),
                    '{module}' => $moduleUnicode->snake()->toString(),
                    '{Module}' => $moduleUnicode->camel()->title()->toString(),
                    '{domain}' => $domainUnicode->snake()->toString(),
                    '{Domain}' => $Domain = $domainUnicode->camel()->title()->toString(),
                    '{action}' => $actionUnicode->snake()->toString(),
                    '{Action}' => $Action = $actionUnicode->camel()->title()->toString(),
                    '{ActionForm}' => $Action,
                    '{tool_name}' => sprintf('%s-%s-%s',
                        $domainUnicode->snake()->replace('_', '-')->toString(),
                        $modelUnicode->snake()->replace('_', '-')->toString(),
                        $actionUnicode->snake()->replace('_', '-')->toString()
                    ),
                ],
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success("[Cortex] Action {$Domain}/{$Model}/{$Action} créé avec succès.");
    }
}
