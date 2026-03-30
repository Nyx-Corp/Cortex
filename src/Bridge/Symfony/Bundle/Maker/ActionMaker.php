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

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    public static function getGeneratedPaths(array $options): array
    {
        $paths = [
            'src/Domain/{Domain}/Error/{Model}Exception.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Command.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Event.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Exception.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Handler.php.tpl.php',
            'src/Domain/{Domain}/Action/{Model}{Action}/Response.php.tpl.php',
        ];

        // MCP Tool
        if ($options['mcp-tool'] ?? false) {
            $paths[] = 'src/Application/{Module}/Controller/Tool/{Model}{Action}Tool.php.tpl.php';
        }

        return array_merge($paths, match ($options['controller']) {
            'form' => [
                'src/Application/{Module}/Form/{Model}{Action}Type.php.tpl.php',
                'src/Application/{Module}/Controller/Action/{Model}{Action}FormAction.php.tpl.php',
            ],
            'list' => [
                'src/Application/{Module}/Controller/Action/{Model}ListAction.php.tpl.php',
            ],
            'model' => [
                'src/Application/{Module}/Controller/Action/{Model}{Action}Action.php.tpl.php',
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
            ->addOption('output-subpath', null, InputOption::VALUE_OPTIONAL, 'Subpath for controllers (Admin, Front, Api)', '')
            ->addOption('root-path', null, InputOption::VALUE_REQUIRED, 'Root path for generated files (e.g., src/Lib/Synapse/src)')
            ->addOption('root-namespace', null, InputOption::VALUE_REQUIRED, 'Root namespace (e.g., Synapse) — rewrites Domain\\, Infrastructure\\, Application\\ prefixes')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $moduleUnicode = u($input->getArgument('module'));
        $domainUnicode = u($input->getArgument('domain'));
        $modelUnicode = u($input->getArgument('model'));
        $actionUnicode = u($input->getArgument('action'));
        $subpathUnicode = u($input->getOption('output-subpath') ?? '');

        $sourcePath = self::getGeneratedPaths($input->getOptions());

        // Compute subpath values for namespace and folder
        $Subpath = $subpathUnicode->camel()->title()->toString();
        $subpath = $subpathUnicode->snake()->toString();
        $subpath_namespace = '' !== $Subpath ? '\\'.$Subpath : '';

        ['destPath' => $destPath, 'namespaceReplacements' => $nsReplacements, 'pathTransformer' => $rootPathTransformer] = $this->resolveRootPath($input, $generator);

        $this->pathCollection
            ->filter(fn (SplFileInfo $file) => in_array(
                $file->getRelativePathname(),
                $sourcePath,
            ))
            ->mirror(
                $destPath,
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
                    '{_action}' => $actionUnicode->snake()->toString(),
                    '{Subpath}' => $Subpath,
                    '{subpath}' => $subpath,
                    '{subpath_namespace}' => $subpath_namespace,
                    '{tool_name}' => sprintf(
                        '%s-%s-%s',
                        $domainUnicode->snake()->replace('_', '-')->toString(),
                        $modelUnicode->snake()->replace('_', '-')->toString(),
                        $actionUnicode->snake()->replace('_', '-')->toString()
                    ),
                ],
                pathTransformer: $rootPathTransformer,
                contentReplacements: $nsReplacements,
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success("[Cortex] Action {$Domain}/{$Model}/{$Action} créé avec succès.");
    }
}
