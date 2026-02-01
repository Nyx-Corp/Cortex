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

final class CrudMaker extends CortexMaker
{
    public static function getCommandName(): string
    {
        return 'make:cortex:crud';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new Cortex CRUD structure with its DDD architecture';
    }

    public static function getGeneratedPaths(array $options): array
    {
        $paths = [
            'templates/{module}/_layout.html.twig',
            'templates/{module}/{model}/_layout.html.twig',

            'src/Application/{Module}/Controller/Action/{Model}ListAction.php',
            'templates/{module}/{model}/index.html.twig',

            'src/Application/{Module}/Controller/Action/{Model}EditAction.php',
            'src/Application/{Module}/Form/{Model}EditType.php',
            'src/Domain/{Domain}/Action/{Model}Edit/Command.php',
            'src/Domain/{Domain}/Action/{Model}Edit/Exception.php',
            'src/Domain/{Domain}/Action/{Model}Edit/Handler.php',
            'src/Domain/{Domain}/Action/{Model}Edit/Response.php',
            'templates/{module}/{model}/_form.html.twig',
            'templates/{module}/{model}/edit.html.twig',
            'templates/{module}/{model}/create.html.twig',

            'src/Application/{Module}/Controller/Action/{Model}ArchiveAction.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Command.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Handler.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Response.php',

            // Tests
            'tests/Functional/Application/{Module}/Controller/{Model}ControllerTest.php',
        ];

        // MCP Tools
        if ($options['mcp-tool'] ?? false) {
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}ListTool.php';
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}CreateTool.php';
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}EditTool.php';
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}ArchiveTool.php';
        }

        return $paths;
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('module', InputArgument::REQUIRED, 'CRUD related module')
            ->addArgument('domain', InputArgument::REQUIRED, 'CRUD related domain')
            ->addArgument('model', InputArgument::REQUIRED, 'CRUD related model')
            ->addOption('mcp-tool', null, InputOption::VALUE_NONE, 'Generate MCP Tools (List, Create, Edit, Archive)')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $moduleUnicode = u($input->getArgument('module'));
        $domainUnicode = u($input->getArgument('domain'));
        $modelUnicode = u($input->getArgument('model'));

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
                    '{Module}' => $Module = $moduleUnicode->camel()->title()->toString(),
                    '{domain}' => $domainUnicode->snake()->toString(),
                    '{Domain}' => $Domain = $domainUnicode->camel()->title()->toString(),
                    '{tool_name_list}' => sprintf(
                        '%s-%s-list',
                        $domainUnicode->snake()->replace('_', '-')->toString(),
                        $modelUnicode->snake()->replace('_', '-')->toString()
                    ),
                    '{tool_name_create}' => sprintf(
                        '%s-%s-create',
                        $domainUnicode->snake()->replace('_', '-')->toString(),
                        $modelUnicode->snake()->replace('_', '-')->toString()
                    ),
                    '{tool_name_edit}' => sprintf(
                        '%s-%s-edit',
                        $domainUnicode->snake()->replace('_', '-')->toString(),
                        $modelUnicode->snake()->replace('_', '-')->toString()
                    ),
                    '{tool_name_archive}' => sprintf(
                        '%s-%s-archive',
                        $domainUnicode->snake()->replace('_', '-')->toString(),
                        $modelUnicode->snake()->replace('_', '-')->toString()
                    ),
                ],
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success("[Cortex] CRUD {$Module} for {$Domain}/{$Model} successfully created.");
    }
}
