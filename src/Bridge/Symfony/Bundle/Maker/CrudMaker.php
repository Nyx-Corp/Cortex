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

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    public static function getGeneratedPaths(array $options): array
    {
        $paths = [
            'templates/{module}/_layout.html.twig.tpl.php',
            'templates/{module}/{model}/_layout.html.twig.tpl.php',

            'src/Application/{Module}/Controller/Action/{Model}ListAction.php.tpl.php',
            'templates/{module}/{model}/index.html.twig.tpl.php',

            // Create
            'src/Application/{Module}/Controller/Action/{Model}CreateAction.php.tpl.php',
            'src/Application/{Module}/Form/{Model}CreateType.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Create/Command.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Create/Event.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Create/Exception.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Create/Handler.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Create/Response.php.tpl.php',
            'templates/{module}/{model}/create.html.twig.tpl.php',

            // Update
            'src/Application/{Module}/Controller/Action/{Model}UpdateAction.php.tpl.php',
            'src/Application/{Module}/Form/{Model}UpdateType.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Update/Command.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Update/Event.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Update/Exception.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Update/Handler.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Update/Response.php.tpl.php',
            'templates/{module}/{model}/_form.html.twig.tpl.php',
            'templates/{module}/{model}/edit.html.twig.tpl.php',

            // Archive
            'src/Application/{Module}/Controller/Action/{Model}ArchiveAction.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Command.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Event.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Handler.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}Archive/Response.php.tpl.php',

            // Tests
            'tests/Functional/Application/{Module}/Controller/{Model}ControllerTest.php.tpl.php',
        ];

        // MCP Tools
        if ($options['mcp-tool'] ?? false) {
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}ListTool.php.tpl.php';
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}CreateTool.php.tpl.php';
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}EditTool.php.tpl.php';
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}ArchiveTool.php.tpl.php';
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
            ->addOption('output-subpath', null, InputOption::VALUE_OPTIONAL, 'Subpath for controllers/templates (Admin, Front, Api)', '')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $moduleUnicode = u($input->getArgument('module'));
        $domainUnicode = u($input->getArgument('domain'));
        $modelUnicode = u($input->getArgument('model'));
        $subpathUnicode = u($input->getOption('output-subpath') ?? '');

        $sourcePath = self::getGeneratedPaths($input->getOptions());

        // Compute subpath values for namespace and folder
        $Subpath = $subpathUnicode->camel()->title()->toString();
        $subpath = $subpathUnicode->snake()->toString();
        $subpath_namespace = '' !== $Subpath ? '\\'.$Subpath : '';

        // Path transformer for subpath: inserts subpath folder in controller and template paths
        $pathTransformer = '' !== $Subpath
            ? fn (string $path) => str_contains($path, 'Controller/Action/')
                ? preg_replace(
                    '#(Controller/Action/)([^/]+Action\.php)#',
                    '$1'.$Subpath.'/$2',
                    $path
                )
                : (str_contains($path, 'templates/')
                    ? preg_replace(
                        '#(templates/[^/]+/)([^/]+/)#',
                        '$1'.strtolower($Subpath).'/$2',
                        $path
                    )
                    : $path)
            : null;

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
                    '{Subpath}' => $Subpath,
                    '{subpath}' => $subpath,
                    '{subpath_namespace}' => $subpath_namespace,
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
                pathTransformer: $pathTransformer,
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success("[Cortex] CRUD {$Module} for {$Domain}/{$Model} successfully created.");
    }
}
