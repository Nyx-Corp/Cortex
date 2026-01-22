<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker;

use Cortex\Bridge\Symfony\Bundle\Maker\Helper\PathCollection;
use Cortex\Bridge\Symfony\Bundle\Maker\Helper\PhpMethod;
use Cortex\Bridge\Symfony\Bundle\Maker\Helper\PhpVar;
use Cortex\Bridge\Symfony\Bundle\Maker\Manipulator\PhpUpdater;
use Cortex\Bridge\Symfony\Bundle\Maker\Manipulator\YamlUpdater;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Symfony\Component\String\u;

final class ModuleMaker extends CortexMaker
{
    public static function getCommandName(): string
    {
        return 'make:cortex:module';
    }

    public static function getCommandDescription(): string
    {
        return 'Crée un module Cortex avec sa structure DDD';
    }

    public static function getGeneratedPaths(): array
    {
        return [
            'config/routes/modules/{module}.yaml',
            'config/routes/application.yaml',
            'src/Application/{Module}/Controller/Action/.gitkeep',
            'templates/{module}/_layout.html.twig',
            'translations/{module}+intl-icu.{locales}.yaml',
        ];
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('name', InputArgument::REQUIRED, 'Nom du module')
            ->addArgument('locales', InputArgument::REQUIRED, 'Locales à générer, séparées par une virgule.')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $moduleUnicode = u($input->getArgument('name'));

        $this->pathCollection
            ->filter(fn (SplFileInfo $file) => in_array(
                $file->getRelativePathname(),
                self::getGeneratedPaths()
            ))
            ->mirror(
                $projectDir = $generator->getRootDirectory(),
                [
                    '{module}' => $module = $moduleUnicode->snake()->toString(),
                    '{Module}' => $Module = $moduleUnicode->camel()->title()->toString(),
                ],
                [
                    '{locales}' => array_map('trim', explode(',', $input->getArgument('locales'))),
                ]
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        PathCollection::create(
            new Finder()
            ->files()
            ->name('application.yaml')
            ->in($projectDir.'/config/routes/')
        )
            ->openYaml(
                fn (YamlUpdater $config) => $config
                    ->addEntry(
                        $moduleUnicode->snake()->toString(),
                        [
                            'resource' => sprintf('./modules/%s.yaml', $module),
                            'name_prefix' => sprintf('%s/', $module),
                            'prefix' => sprintf('/%s', $module),
                            'host' => '%app.host%',
                            'defaults' => ['_format' => 'html'],
                            'options' => ['module' => $module],
                        ],
                        beforeKey: 'index'
                    )
                    ->save()
            )
            ->generate(fn (SplFileInfo $updatedFile) => $io->text(sprintf(
                '  ↻ <info>updated</info> <comment>%s</comment>',
                $updatedFile->getRealPath()
            )))
        ;

        // PathCollection::create(new Finder()
        //     ->files()
        //     ->name('ShootingController.php')
        //     ->in($projectDir.'/src/Application/Studio/Controller/')
        // )
        //     ->openPhpClass(fn (PhpUpdater $updater) => $updater
        //         ->addMethod(new PhpMethod(
        //             doc: 'Generate a shooting session based on the provided context and debug flag.',
        //             name: 'generate',
        //             parameters: [
        //                 new PhpVar('array', 'context', 'Context for generating the shooting session.'),
        //                 new PhpVar('bool', 'debug', 'Flag to enable debug mode.', false),
        //             ],
        //             returnType: new PhpVar(
        //                 fqcn: ResponseInterface::class,
        //                 doc: 'Http response for the generated shooting session.'
        //             ),
        //             body: <<<PHP
        //                 // This method would handle the logic for generating something related to shooting sessions.
        //                 // The implementation is currently a placeholder.
        //             PHP
        //         ))
        //         ->save()
        //     )
        //     ->map(fn (SplFileInfo $updatedFile) => $io->text(sprintf(
        //         '  ↻ <info>updated</info> <comment>%s</comment>',
        //         $updatedFile->getRealPath()
        //     )))
        //     ->toArray()
        // ;

        $io->success("[Cortex] Module '{$Module}' créé avec succès.");
    }
}
